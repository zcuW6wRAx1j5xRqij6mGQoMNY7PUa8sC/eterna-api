<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Internal\Order\Actions\CloseFuturesOrder;
use Internal\Order\Actions\ClosePositionEngineCallback;

class HandelEngineClosePositionCallabck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $data)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach ($this->data as $item) {
            try {
                (new ClosePositionEngineCallback)($item);
            } catch(\Throwable $e) {
                Log::error('处理平仓信息失败 : '.$e->getMessage(),['item'=>$item]);
            }
        }
    }
}
