<?php

namespace Internal\Financial\Actions;

use App\Enums\CoinEnums;
use App\Enums\FinancialEnums;
use App\Enums\FundsEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Exceptions\LogicException;
use App\Models\Financial;
use App\Models\UserOrderFinancial;
use App\Models\UserOrderFinancialLog;
use App\Models\UserWalletSpot;
use App\Models\UserWalletSpotFlow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettlementFinancial
{

    public function __invoke(UserOrderFinancial $order)
    {
        return DB::transaction(function () use ($order) {
            $now = Carbon::now();

            if ($order->status != FinancialEnums::StatusPending) {
                Log::error('投资计算失败 ,订单状态不正确', [
                    'order_id' => $order->id,
                    'status' => $order->status,
                ]);
                throw new LogicException('投资计算失败 ,订单状态不正确');
            }

            $product = Financial::find($order->financial_id);
            if (!$product) {
                Log::error('投资计算失败 ,产品不存在', [
                    'order_id' => $order->id,
                    'info' => $order->toArray()
                ]);
                throw new LogicException('投资计算失败 ,产品不存在');
            }

            // 查看今日是否已经结算完成
            if (UserOrderFinancialLog::where('order_id', $order->id)->where('settle_date', $now->toDateString())->exists()) {
                Log::error('投资计算失败 ,今日已经结算完成', [
                    'order_id' => $order->id,
                    'info' => $order->toArray()
                ]);
                throw new LogicException('投资计算失败 ,今日已经结算完成');
            }

            // 单天的收益金额
            $profit = bcdiv(
                bcmul($product->min_daily_rate, $order->amount, FundsEnums::DecimalPlaces),
                100,
                FundsEnums::DecimalPlaces
            );

            // 是否最后一天, 需要返回本金
            $existsSettlementCount = UserOrderFinancialLog::where('order_id', $order->id)->count();
            $lastDay = false;
            if ($existsSettlementCount + 1 >= $order->duration) {
                $lastDay = true;
            }
            if ($lastDay) {
                $order->settled_at = $now;
                $order->status = FinancialEnums::StatusSettled;
                $profit = bcadd($profit, $order->amount, FundsEnums::DecimalPlaces);
            }

            $order->settled_total_profit = bcadd($order->settled_total_profit, $profit, FundsEnums::DecimalPlaces);
            $order->save();

            // 打钱
            $wallet = UserWalletSpot::where('uid', $order->uid)->where('coin_id', CoinEnums::DefaultUSDTCoinID)->lockForUpdate()->first();
            if (!$wallet) {
                throw new LogicException(__('计算失败, 用户现货钱包不存在'));
            }
            $d = bcadd($wallet->amount, $profit, FundsEnums::DecimalPlaces);
            $before = $wallet->amount;
            $wallet->amount = $d;
            $wallet->save();

            $flow = new UserWalletSpotFlow();
            $flow->uid = $order->uid;
            $flow->coin_id = CoinEnums::DefaultUSDTCoinID;
            $flow->flow_type = SpotWalletFlowEnums::FlowTypeFinancialSettle;
            $flow->before_amount = $before;
            $flow->amount = $profit;
            $flow->after_amount = $wallet->amount;
            $flow->relation_id = 0;
            $flow->save();


            $log = new UserOrderFinancialLog();
            $log->order_id = $order->id;
            $log->uid = $order->uid;
            $log->settle_date = $now->toDateString();
            $log->amount = $profit;
            $log->save();
            return true;
        });
    }
}
