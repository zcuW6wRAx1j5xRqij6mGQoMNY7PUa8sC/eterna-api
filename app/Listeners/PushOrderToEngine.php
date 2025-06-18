<?php

namespace App\Listeners;

use App\Enums\OrderEnums;
use App\Events\NewSpotOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class PushOrderToEngine implements ShouldQueue
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
        $order = $event->userSpotOrder;
        if ($order->trade_type != OrderEnums::TradeTypeLimit) {
            return;
        }
        if ($order->trade_status != OrderEnums::SpotTradeStatusProcessing ) {
            return;
        }

        $key = $order->side == OrderEnums::SideBuy ? sprintf(OrderEnums::SpotBuyLimitOrderKey, $order->symbol->symbol) : sprintf(OrderEnums::SpotSellLimitOrderKey, $order->symbol->symbol);
        RedisPosition()->zadd($key, (string)$order->price, $order->id);
        return;
    }
}
