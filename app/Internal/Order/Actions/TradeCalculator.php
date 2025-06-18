<?php

namespace Internal\Order\Actions;

use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Http\Resources\UserOrderSpotCollection;
use App\Models\UserOrderFutures;
use App\Models\UserOrderSpot;
use App\Models\UserWalletFutures;
use DivisionByZeroError;
use Illuminate\Http\Request;
use League\CommonMark\Node\Query\OrExpr;

class TradeCalculator
{

    // 强平可用保证金比例 = 3%
    const ForceClosesMarginRate = 0.03;

    /**
     * 计算保证金
     * @param mixed $volume
     * @param mixed $leverage
     * @return string
     * @throws DivisionByZeroError
     */
    public function calcuMargin($tradeVolume, $leverage)
    {
        // 废弃
        // 目前保证金计算为最基础部分  : 交易额 / 杠杆
        return bcdiv($tradeVolume, $leverage, FundsEnums::MarginDecimalPlaces);
    }

    /**
     * 计算保证金比例
     * @param UserOrderFutures $userOrderFutures
     * @param UserWalletFutures $userWalletFutures
     * @param mixed $latestPrice
     * @return string|int
     * @throws DivisionByZeroError
     */
    public function calcuMarginRatio(UserOrderFutures $userOrderFutures, UserWalletFutures $userWalletFutures, $profit =0 )
    {
        switch ($userOrderFutures->margin_type) {
            // v1 全仓( 已使用保证金 + 未结算盈亏 / 账户总资产 ) * 100
            // v2 全仓可用保证金比例 : (可用余额 - (已使用保证金 + 未结算盈亏))  / 可用余额 * 100
            case OrderEnums::MarginTypeCrossed:
                $totalPer = bcadd($userWalletFutures->balance, $userOrderFutures->margin, FundsEnums::DecimalPlaces);
                $d = bcdiv(
                    bcsub($totalPer,bcadd($userOrderFutures->margin, $profit, FundsEnums::DecimalPlaces),FundsEnums::DecimalPlaces),
                    $totalPer,
                    FundsEnums::DecimalPlaces
                );
                return bcmul($d , 100, FundsEnums::MarginDecimalPlaces);

            //v1 逐仓 : ((合约量 * 当前市价格) / 已使用保证金) * 100%
            // v2 逐仓 : ((保证金 - 未结算盈亏) / 保证金) * 100
            case OrderEnums::MarginTypeIsolated:
                $d = bcdiv(
                    bcsub($userOrderFutures->margin, $profit, FundsEnums::DecimalPlaces),
                    $userOrderFutures->margin,
                    FundsEnums::DecimalPlaces
                );
                return bcmul($d, 100, FundsEnums::DecimalPlaces);
                break;
            default:
                return 0;
        }
    }

    /**
     * 计算收益
     * @param UserOrderFutures $userOrderFutures
     * @param mixed $latestPrice
     * @return float|int|string
     */
    public function calcuProfit(UserOrderFutures $userOrderFutures, $latestPrice)
    {
        if ($latestPrice == 0) {
            return 0;
        }
        $sub = bcsub($latestPrice, $userOrderFutures->open_price, FundsEnums::DecimalPlaces);
        if ($sub == 0) {
            return 0;
        }
        $profit = bcmul(
            $sub,
            $userOrderFutures->volume,
            FundsEnums::DecimalPlaces,
        );
        if ($userOrderFutures->side == OrderEnums::SideSell) {
            $profit = -$profit;
        }
        return $profit;
    }

    /**
     * 计算收益率
     * @param UserOrderFutures $userOrderFutures
     * @param mixed $latestPrice
     * @return int|string
     * @throws DivisionByZeroError
     */
    public function calcuProfitRatio(UserOrderFutures $userOrderFutures, $latestPrice)
    {
        // 盈亏 / 保证金
        $profit = $this->calcuProfit($userOrderFutures, $latestPrice);
        if ($profit == 0) {
            return 0;
        }
        return bcdiv($profit, $userOrderFutures->margin, FundsEnums::MarginDecimalPlaces);
    }

    /**
     * 计算强平价格
     * @param UserOrderFutures $userOrderFutures
     * @param int $latestPrice
     * @return int|string
     * @throws DivisionByZeroError
     */
    public function calcuForceClosePrice(UserOrderFutures $userOrderFutures)
    {
        // 逐仓强平价格计算
        // 多头 开仓价格 * (1 - (保证金 / (当前价格 * 交易量))
        // 空头 开仓价格 * (1 + (保证金 / (当前价格 * 交易量))

        // 不计算 全仓 强平价格
        if ($userOrderFutures->margin_type == OrderEnums::MarginTypeCrossed) {
            return 0;
        }

        $base = bcdiv(
            $userOrderFutures->margin,
            bcmul($userOrderFutures->open_price, $userOrderFutures->volume, FundsEnums::DecimalPlaces),
            FundsEnums::DecimalPlaces
        );
        $base = $userOrderFutures->side == OrderEnums::SideBuy ? bcsub(1, $base, FundsEnums::DecimalPlaces) : bcadd(1, $base, FundsEnums::DecimalPlaces);
        return bcmul(
            $userOrderFutures->open_price,
            $base,
            FundsEnums::DecimalPlaces,
        );
    }

    /**
     * 计算交易量
     * @param mixed $tradeVolume
     * @param mixed $openPrice
     * @return string
     * @throws DivisionByZeroError
     */
    public function calcuVolume($tradeVolume, $openPrice)
    {
        // 交易额 / 开仓价格
        return bcdiv(
            $tradeVolume,
            $openPrice,
            FundsEnums::DecimalPlaces
        );
    }
}
