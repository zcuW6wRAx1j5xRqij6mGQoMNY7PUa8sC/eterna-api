<?php

namespace App\Listeners;

use App\Enums\OrderEnums;
use App\Events\NewFuturesOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class PushFuturesLimitOrderToEngine implements ShouldQueue
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
    public function handle(NewFuturesOrder $event): void
    {
        $order = $event->userOrderFutures;
        if ($order->trade_type != OrderEnums::TradeTypeLimit) {
            return;
        }
        if ($order->trade_status != OrderEnums::SpotTradeStatusProcessing ) {
            return;
        }

        $key = $order->side == OrderEnums::SideBuy ? sprintf(OrderEnums::FuturesBuyLimitOrders, $order->symbol->symbol) : sprintf(OrderEnums::FuturesSellLimitOrders, $order->symbol->symbol);
        RedisPosition()->zadd($key, (string)$order->price, $order->id);
        return;
    }
}
