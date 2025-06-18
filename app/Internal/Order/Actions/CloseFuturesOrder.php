<?php

namespace Internal\Order\Actions;

use App\Enums\ConfigEnums;
use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Enums\WalletFuturesFlowEnums;
use App\Events\UserFuturesBalanceUpdate;
use App\Exceptions\LogicException;
use App\Jobs\SendRefreshOrder;
use App\Models\User;
use App\Models\UserOrderFutures;
use App\Models\UserWalletFutures;
use App\Models\UserWalletFuturesFlow;
use App\Models\UserWalletFuturesSummary;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Internal\Common\Services\ConfigService;
use Internal\Market\Actions\FetchSymbolFuturesQuote;
use Internal\Market\Services\BinanceService;

class CloseFuturesOrder {

    public function __invoke(int $orderId , $price = 0, string $closeType = OrderEnums::FuturesCloseTypeNormal)
    {
        return DB::transaction(function() use($orderId, $price, $closeType){
            $order = UserOrderFutures::lockForUpdate()->find($orderId);
            if (!$order) {
                throw new LogicException(__('Whoops! Something went wrong'));
            }

            if ($order->trade_status !== OrderEnums::FuturesTradeStatusOpen) {
                throw new LogicException(__('The Position status is incorrect'));
            }

            $marketPrice = $price;
            if (!$marketPrice) {
                // 获取最新报价
                $marketPrice = (new FetchSymbolFuturesQuote)($order->symbol->symbol);
                if (!$marketPrice) {
                    Log::error('failed to create spot order : no quote',[
                        'symbolId'=>$order->symbol->id,
                    ]);
                    throw new LogicException(__('Whoops! Something went wrong'));
                }
            }

            // 交易价格
            $price = $marketPrice;
            $calcu = new TradeCalculator;
            $profit = $calcu->calcuProfit($order, $price);
            $profit_ratio = $calcu->calcuProfitRatio($order,$price);

            $fee = ConfigService::getIns()->fetch(ConfigEnums::PlatformConfigFuturesCloseFee, 0);
            if ($fee) {
                $fee = bcmul(
                    $order->trade_volume, 
                    bcdiv($fee, 100, FundsEnums::DecimalPlaces),
                    FundsEnums::DecimalPlaces
                );
            }

            // 处理资金
            $wallet = UserWalletFutures::where('uid', $order->uid)->lockForUpdate()->first();
            if (!$wallet) {
                // 一般不会进入到这里
                Log::error('failed to create spot order : not found user wallet model',[
                    'uid'=>$order->uid,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            $realProfit = $profit;
            $totalPrice = bcadd($order->margin, $profit, FundsEnums::DecimalPlaces);
            $d = bcadd($wallet->balance, $totalPrice, FundsEnums::DecimalPlaces);
            if ($d < 0) {
                Log::warning('用户平仓时, 提前爆仓, 平台亏损 : '.abs($d),[
                    'uid'=>$order->uid,
                    'order_id'=>$order->id,
                    'margin'=>$order->margin,
                    'profit'=>$profit,
                    'fee'=>$fee,
                    'total_price'=>$totalPrice,
                ]);
                $d = 0;
                $realProfit = - bcadd($wallet->balance ,$order->margin, FundsEnums::DecimalPlaces);
            }
            $before = $wallet->balance;
            $wallet->balance = $d;

            $lb = bcsub($wallet->lock_balance, $order->margin, FundsEnums::DecimalPlaces);
            if ($lb < 0 ) {
                $lb = 0;
            }
            $wallet->lock_balance = $lb;
            $wallet->save();

            // 扣除手续费
            $wallet->balance = bcsub($wallet->balance, $fee, FundsEnums::DecimalPlaces);
            if ($wallet->balance < 0) {
                Log::warning('用户平仓时, 扣除手续费导致余额小于0 : '.abs($d),[
                    'uid'=>$order->uid,
                    'order_id'=>$order->id,
                    'margin'=>$order->margin,
                    'profit'=>$profit,
                    'fee'=>$fee,
                    'total_price'=>$totalPrice,
                ]); 
                $wallet->balance = 0;
            }
            $wallet->save();

            $order->close_price = $price;
            $order->close_time = Carbon::now();
            $order->close_spread = 0;
            $order->close_fee = $fee;
            $order->close_type = $closeType;
            $order->profit = $realProfit;
            $order->profit_ratio = $profit_ratio;
            $order->trade_status = OrderEnums::FuturesTradeStatusClosed;
            $order->save();


            $flow = new UserWalletFuturesFlow();
            $flow->uid = $order->uid;
            $flow->flow_type = WalletFuturesFlowEnums::FlowPositionClose;
            $flow->before_amount = $before;
            $flow->amount = bcsub($totalPrice, $fee, FundsEnums::DecimalPlaces);
            $flow->after_amount = $wallet->balance;
            $flow->relation_id = $order->id;
            $flow->save();

            $todaySummary = UserWalletFuturesSummary::where('uid', $order->uid)->where('summary_date', Carbon::now()->toDateString())->first();
            if (!$todaySummary) {
                $todaySummary = new UserWalletFuturesSummary();
                $todaySummary->uid = $order->uid;
                $todaySummary->total_profit = $realProfit;
                $todaySummary->summary_date = Carbon::now()->toDateString();
                $todaySummary->save();
            } else {
                $todaySummary->total_profit = bcadd($todaySummary->total_profit, $realProfit, FundsEnums::DecimalPlaces);
                $todaySummary->save();
            }

            // 取消持仓监控
            (new MonitorPosition)->cancel($order);
            UserFuturesBalanceUpdate::dispatch($order->uid,floatTransferString($wallet->balance));
            SendRefreshOrder::dispatch(User::find($order->uid));
            return true;
        });
    }
}

