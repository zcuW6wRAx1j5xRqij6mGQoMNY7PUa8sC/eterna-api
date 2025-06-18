<?php

namespace Internal\Order\Actions;

use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Enums\WalletFuturesFlowEnums;
use App\Events\CancelOrderFuturesProcess;
use App\Events\UserFuturesBalanceUpdate;
use App\Exceptions\LogicException;
use App\Models\UserOrderFutures;
use App\Models\UserWalletFutures;
use App\Models\UserWalletFuturesFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelFuturesOrder {
    public function __invoke(Request $request)
    {
        return DB::transaction(function()use($request){
            $orderId = $request->get('order_id');
            $order = UserOrderFutures::find($orderId);
            if (!$order) {
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            if ($order->trade_type !== OrderEnums::TradeTypeLimit) {
                throw new LogicException(__('Incorrect order type'));
            }
            if ($order->trade_status !== OrderEnums::FuturesTradeStatusProcessing) {
                throw new LogicException(__('Incorrect order status'));
            }
            if ($order->uid != $request->user()->id) {
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            $order->trade_status = OrderEnums::FuturesTradeStatusCancel;
            $order->save();

            // 退钱
            $wallet = UserWalletFutures::where('uid', $request->user()->id)->lockForUpdate()->first();
            if (!$wallet) {
                Log::error('failed to cancel processing order : no found user wallet',[
                    'uid'=>$request->user()->id,
                    'order_id'=>$orderId,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            $d = bcsub($wallet->lock_balance, $order->margin, FundsEnums::DecimalPlaces);
            if ($d < 0 ) {
                Log::error('failed to cancel processing order : insufficient lock balance',[
                    'balance'=>$wallet->lock_balance,
                    'refund_amount'=>$order->trade_volume,
                    'uid'=>$request->user()->id,
                    'order_id'=>$orderId,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            $before = $wallet->balance;
            $wallet->balance = bcadd($wallet->balance, $order->margin,FundsEnums::DecimalPlaces);
            $wallet->lock_balance = $d;
            $wallet->save();


            $flow = new UserWalletFuturesFlow();
            $flow->uid = $request->user()->id;
            $flow->flow_type = WalletFuturesFlowEnums::FlowTypeRefundPostingOrder;
            $flow->before_amount = $before;
            $flow->amount = $order->margin;
            $flow->after_amount = $wallet->balance;
            $flow->relation_id = $order->id;
            $flow->save();

            CancelOrderFuturesProcess::dispatch($order);
            UserFuturesBalanceUpdate::dispatch($order->uid,floatTransferString($wallet->balance));
            return true;

        });
    }
}

