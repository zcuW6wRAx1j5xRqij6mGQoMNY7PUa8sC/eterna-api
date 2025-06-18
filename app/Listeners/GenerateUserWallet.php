<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Models\PlatformCoin;
use App\Models\UserDerivativelWallet;
use App\Models\UserSpotWallet;
use App\Models\UserSpotWalletCoin;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Internal\User\Actions\InitUserWallet;

class GenerateUserWallet implements ShouldQueue
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
    public function handle(UserCreated $event): void
    {
        (new InitUserWallet)($event->user);
        return ;
    }
}
