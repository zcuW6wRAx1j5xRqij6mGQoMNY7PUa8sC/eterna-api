<?php

namespace App\Listeners;

use App\Enums\OrderEnums;
use App\Events\NewFuturesOrder;
use App\Events\UpdateFuturesOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Internal\Order\Actions\MonitorPosition;

class FuturesOrderMonitor implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UpdateFuturesOrder $event): void
    {
        if ($event->userOrderFutures->trade_status == OrderEnums::FuturesTradeStatusOpen) {
            (new MonitorPosition)($event->userOrderFutures);
        }
    }
}
