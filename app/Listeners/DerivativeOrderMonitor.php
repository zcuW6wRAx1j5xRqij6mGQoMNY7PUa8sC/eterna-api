<?php

namespace App\Listeners;

use App\Enums\OrderEnums;
use App\Events\NewDerivativeOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Internal\Order\Actions\MonitorPosition;

class DerivativeOrderMonitor implements ShouldQueue
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
    public function handle(NewDerivativeOrder $event): void
    {
        (new MonitorPosition)($event->userDerivativelPosition);
        return;
    }
}
