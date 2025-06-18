<?php

namespace App\Jobs;

use App\Models\PlatformSymbolPrice;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Internal\Market\Actions\SetFakePrice;

class StopFakePrice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public PlatformSymbolPrice $platformSymbolPrice)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        (new SetFakePrice)->jobCancel($this->platformSymbolPrice);
        return;
    }
}
