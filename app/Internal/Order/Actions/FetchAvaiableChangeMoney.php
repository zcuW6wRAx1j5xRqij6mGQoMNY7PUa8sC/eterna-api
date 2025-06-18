<?php

namespace Internal\Order\Actions;

use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Models\User;
use App\Models\UserOrderFutures;
use App\Models\UserWalletFutures;
use Illuminate\Support\Facades\Log;
use Internal\Market\Actions\FetchSymbolFuturesQuote;

class FetchAvaiableChangeMoney
{

    public function __invoke(User $user)
    {
        $futures = UserWalletFutures::where('uid', $user->id)->lockForUpdate()->first();
        if (!$futures) {
            Log::error('计算合约账户可划转金额失败 , 没有找到用户合约账户', ['uid' => $user->id]);
            return 0;
        }

        // 获取所有合约交易价格
        $allSymbols = (new FetchSymbolFuturesQuote)->getAllSymbol();
        if (!$allSymbols) {
            Log::error('计算合约账户可划转金额失败 , 没有找到交易对报价信息', ['uid' => $user->id]);
            return 0;
        }

        $calc = new TradeCalculator;
        $orders = UserOrderFutures::with(['symbol'])->where('uid', $user->id)->where('trade_status', OrderEnums::FuturesTradeStatusOpen)->get();
        if ($orders->isEmpty()) {
            return $futures->balance;
        }

        $totalProfit = 0;
        $orders->each(function ($item) use ($calc, $allSymbols, &$totalProfit) {
            $binance = $item->symbol->binance_symbol ?? '';
            if (!$binance) {
                return true;
            }
            $curQuote = $allSymbols[strtolower($binance)] ?? 0;
            if (! $curQuote) {
                return true;
            }
            $profit = $calc->calcuProfit($item, $curQuote);
            $totalProfit = bcadd($totalProfit, $profit, FundsEnums::DecimalPlaces);
            return true;
        });

        
        if ($totalProfit >= 0) {
            return $futures->balance;
        }

        $d = bcsub($futures->balance, abs($totalProfit), FundsEnums::DecimalPlaces);
        if ($d <= 0) {
            return 0;
        }
        return $d;
    }
}
