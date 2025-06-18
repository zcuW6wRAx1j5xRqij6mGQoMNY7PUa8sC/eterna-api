<?php

namespace Internal\Order\Actions;

use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Enums\WalletFuturesFlowEnums;
use App\Events\UpdateFuturesOrder;
use App\Exceptions\LogicException;
use App\Models\UserOrderFutures;
use App\Models\UserWalletFutures;
use App\Models\UserWalletFuturesFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Internal\Market\Actions\FetchSymbolFuturesQuote;

// 补仓
class AverageDown {

    public function __invoke(Request $request)
    {
        return DB::transaction(function() use($request){
            $orderId = $request->get('order_id');
            // 只能整数位补仓
            $amount = intval($request->get('amount'));
            if ($amount == 0) {
                throw new LogicException(__('The amount is incorrect'));
            }

            $order = UserOrderFutures::with(['symbol'])->lockForUpdate()->find($orderId);
            if (!$order) {
                throw new LogicException(__('Whoops! Something went wrong'));
            }

            if ($order->trade_status !== OrderEnums::FuturesTradeStatusOpen) {
                throw new LogicException(__('The Order status is incorrect'));
            }
            if ($order->margin_type != OrderEnums::MarginTypeIsolated) {
                throw new LogicException(__('Whoops! Something went wrong'));
            }

            $wallet = UserWalletFutures::where('uid', $request->user()->id)->lockForUpdate()->first();
            if (!$wallet) {
                // 一般不会进入到这里
                Log::error('failed to create spot order : not found user wallet model',[
                    'uid'=>$order->uid,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }

            // 检查余额或保证金是否足够
            if ($amount > 0) {
                $before = $wallet->balance;
                $d = bcsub($wallet->balance, $amount, FundsEnums::DecimalPlaces);
                if ($d < 0) {
                    throw new LogicException(__('Insufficient account balance'));
                }
                $wallet->balance = $d;
                $wallet->lock_balance = bcadd($wallet->lock_balance, $amount, FundsEnums::DecimalPlaces);
                $wallet->save();

                // 增加流水信息
                $flow = new UserWalletFuturesFlow();
                $flow->uid = $request->user()->id;
                $flow->flow_type = WalletFuturesFlowEnums::FlowIncreaseMargin;
                $flow->before_amount = $before;
                $flow->amount = $amount;
                $flow->after_amount = $wallet->balance;
                $flow->relation_id = $order->id;
                $flow->save();

                $order->margin = bcadd($order->margin , $amount, FundsEnums::DecimalPlaces);
                $order->save();

            } else {
                $amount = abs($amount);
                $d = bcsub($order->margin, $amount, FundsEnums::MarginDecimalPlaces);
                if ($d < 0) {
                    throw new LogicException(__('Insufficient margin'));
                }
                $lb = bcsub($wallet->lock_balance, $amount,FundsEnums::DecimalPlaces);
                if ($lb < 0) {
                    Log::error('用户减仓失败 , 锁定余额不够扣除',['uid'=>$order->uid,'lock_balance'=>$wallet->lock_balance,'reduce'=>$amount]);
                    throw new LogicException(__('Whoops! Something went wrong'));
                }

                $before = $wallet->balance;
                $order->margin = $d;
                $order->save();
                $wallet->balance = bcadd($wallet->balance, $amount, FundsEnums::DecimalPlaces);
                $wallet->lock_balance = $lb;
                $wallet->save();

                // 增加流水信息
                $flow = new UserWalletFuturesFlow();
                $flow->uid = $request->user()->id;
                $flow->flow_type = WalletFuturesFlowEnums::FlowReduceMargin;
                $flow->before_amount = $before;
                $flow->amount = $amount;
                $flow->after_amount = $wallet->balance;
                $flow->relation_id = $order->id;
                $flow->save();
            }

            $calcu = new TradeCalculator;
            // 先处理一次盈亏

            $marketPrice = (new FetchSymbolFuturesQuote)($order->symbol->symbol);
            if (!$marketPrice) {
                Log::error('补仓失败 : 没有找到报价数据',[
                    'symbol'=>$order->symbol->symbol,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            $order->profit = $calcu->calcuProfit($order,$marketPrice);
            $order->profit_ratio = $calcu->calcuProfitRatio($order,$marketPrice);
            $order->margin_ratio = $calcu->calcuMarginRatio($order,$wallet, $order->profit);

            $order->force_close_price = $calcu->calcuForceClosePrice($order);
            $order->save();

            UpdateFuturesOrder::dispatch($order);
            return true;

        });
    }
}

