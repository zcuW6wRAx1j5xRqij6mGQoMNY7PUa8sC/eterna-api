<?php

namespace App\Providers;

use App\Events\AuditWithdraw;
use App\Events\NewDeposit;
use App\Events\NewDerivativeOrder;
use App\Events\NewFuturesOrder;
use App\Events\NewIdentitySubmit;
use App\Events\NewSpotOrder;
use App\Events\UpdateFuturesOrder;
use App\Events\UserChangePassword;
use App\Events\UserCreated;
use App\Events\UserFuturesBalanceUpdate;
use App\Events\UserNewMsg;
use App\Listeners\DepositNotify;
use App\Listeners\DepositUserWallet;
use App\Listeners\DerivativeOrderMonitor;
use App\Listeners\FuturesOrderMonitor;
use App\Listeners\GenerateUserInviteCode;
use App\Listeners\GenerateUserWallet;
use App\Listeners\HandleAuditWithdraw;
use App\Listeners\NewUserNotice;
use App\Listeners\PushFuturesLimitOrderToEngine;
use App\Listeners\PushOrderToEngine;
use App\Listeners\ReCalculateSpotWallet;
use App\Listeners\UpdateFuturesBalanceCache;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        UserCreated::class => [
            NewUserNotice::class,
            GenerateUserInviteCode::class,
            GenerateUserWallet::class,
        ],
        UserChangePassword::class =>[],
        NewIdentitySubmit::class => [],
        NewFuturesOrder::class=>[
            PushFuturesLimitOrderToEngine::class,
        ],
        UpdateFuturesOrder::class=>[
            FuturesOrderMonitor::class,
        ],
        NewDerivativeOrder::class => [
            DerivativeOrderMonitor::class,
        ],
        UserNewMsg::class => [],
        NewSpotOrder::class => [
            PushOrderToEngine::class,
            ReCalculateSpotWallet::class,
        ],
        AuditWithdraw::class => [
            HandleAuditWithdraw::class,
        ],
        NewDeposit::class=>[
            DepositUserWallet::class,
            DepositNotify::class,
        ],
        UserFuturesBalanceUpdate::class => [
            UpdateFuturesBalanceCache::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
