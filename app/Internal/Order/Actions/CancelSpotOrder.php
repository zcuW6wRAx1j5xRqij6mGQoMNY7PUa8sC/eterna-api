<?php

namespace Internal\Order\Actions;

use App\Enums\CoinEnums;
use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Events\CancelOrderSpotProcess;
use App\Exceptions\LogicException;
use App\Models\UserOrderSpot;
use App\Models\UserWalletSpot;
use App\Models\UserWalletSpotFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelSpotOrder {
    public function __invoke(Request $request)
    {
        return DB::transaction(function()use($request){
            $orderId = $request->get('order_id');
            $order = UserOrderSpot::find($orderId);
            if (!$order) {
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            if ($order->trade_type !== OrderEnums::TradeTypeLimit) {
                throw new LogicException(__('Incorrect order type'));
            }
            if ($order->trade_status !== OrderEnums::SpotTradeStatusProcessing) {
                throw new LogicException(__('Incorrect order status'));
            }
            if ($order->uid != $request->user()->id) {
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            $order->trade_status = OrderEnums::SpotTradeStatusFailed;
            $order->save();

            $baseCoinId = CoinEnums::DefaultUSDTCoinID;
            $baseQuantity = $order->trade_volume;
            if ($order->side == OrderEnums::SideSell) {
                $baseCoinId = $order->symbol->coin_id;
                $baseQuantity = $order->volume;
            }

            // 退钱
            $spot = UserWalletSpot::where('uid', $request->user()->id)->where('coin_id',$baseCoinId)->lockForUpdate()->first();
            if (!$spot) {
                Log::error('failed to cancel processing order : no found user wallet',[
                    'uid'=>$request->user()->id,
                    'order_id'=>$orderId,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            $d = bcsub($spot->lock_amount, $baseQuantity, FundsEnums::DecimalPlaces);
            if ($d < 0 ) {
                Log::error('failed to cancel processing order : insufficient lock blance',[
                    'balance'=>$spot->amount,
                    'refund_amount'=>$order->trade_volume,
                    'uid'=>$request->user()->id,
                    'order_id'=>$orderId,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            $before = $spot->amount;
            $spot->amount = bcadd($spot->amount, $baseQuantity,FundsEnums::DecimalPlaces);
            $spot->lock_amount = $d;
            $spot->save();


            $flow = new UserWalletSpotFlow();
            $flow->uid = $request->user()->id;
            $flow->coin_id = $baseCoinId;
            $flow->flow_type = SpotWalletFlowEnums::FlowTypeRefundPostingOrder;
            $flow->before_amount = $before;
            $flow->amount = $baseQuantity;
            $flow->after_amount = $spot->amount;
            $flow->relation_id = $order->id;
            $flow->save();

            CancelOrderSpotProcess::dispatch($order);
            return true;

        });
    }
}

