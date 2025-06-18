<?php

namespace App\Listeners;

use App\Events\UserFuturesBalanceUpdate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Internal\Wallet\Actions\UpdateUserFuturesBalanceCache;

class UpdateFuturesBalanceCache
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
    public function handle(UserFuturesBalanceUpdate $event): void
    {
        (new UpdateUserFuturesBalanceCache)($event->uid, $event->latestPirce);
    }
}
