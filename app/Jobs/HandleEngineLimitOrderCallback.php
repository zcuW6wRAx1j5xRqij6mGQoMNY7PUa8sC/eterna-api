<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Internal\Order\Actions\LimitEngineCallback;

class HandleEngineLimitOrderCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $data)
    {
        // example
        // $data = [
        //     'order_type'=>'',
        //     'order_ids'=>[],
        //     'price'=>0
        // ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        (new LimitEngineCallback)($this->data);
    }
}
