<?php

namespace App\Listeners;

use App\Events\NewSpotOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ReCalculateSpotWallet
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
    public function handle(NewSpotOrder $event): void
    {
        //
    }
}
