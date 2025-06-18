<?php

namespace Internal\Order\Actions;

use App\Enums\OrderEnums;
use App\Events\UpdateFuturesOrder;
use App\Exceptions\LogicException;
use App\Models\UserOrderFutures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModifySLTP {

    public function __invoke(Request $request)
    {
        return DB::transaction(function() use($request){

            $orderId = $request->get('order_id');
            $sl = abs(intval( $request->get('sl',0)));
            $tp = abs(intval( $request->get('tp',0)));

            $order = UserOrderFutures::lockForUpdate()->find($orderId);
            if (!$order) {
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            if ($order->trade_status !== OrderEnums::FuturesTradeStatusOpen) {
                throw new LogicException(__('The Order status is incorrect'));
            }

            if ($sl) {
                $order->sl = $sl;
            }
            if ($tp) {
                $order->tp = $tp;
            }
            $order->save();

            UpdateFuturesOrder::dispatch($order);
            return true;
        });
    }
}
