<?php

namespace App\Jobs;

use AWS\CRT\Log as CRTLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Internal\Order\Actions\CloseDerivativeOrder;

class ReceiveClosePosition implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $data = [];

    /**
     * Create a new job instance.
     */
    public function __construct(array $data )
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->data) {
            Log::error('failed to handle auto close position, no data');
            return;
        }
        foreach ($this->data as $item) {
            $orderId = $item['orderId'] ?? 0;
            $price = $item['price'] ?? '';
            $closeType = $item['closeType'] ?? '';

            if (!$orderId || !$price || !$closeType) {
                Log::error("failed to handle auto close position, data incorrect",[
                    'all'=>$this->data,
                ]);
                continue;
            }

            try {
                (new CloseDerivativeOrder)($orderId,$price,$closeType);
            } catch(\Throwable $e) {
                Log::error('failed to auto close position : '.$e->getMessage(),[
                    'orderId'=>$orderId,
                    'price'=>$price,
                    'closeType'=>$closeType
                ]);
            }
        }
        return;
    }
}
