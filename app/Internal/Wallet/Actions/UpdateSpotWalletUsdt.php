<?php

namespace Internal\Wallet\Actions;

use App\Enums\CoinEnums;
use App\Enums\FundsEnums;
use App\Models\SymbolCoin;
use App\Models\User;
use App\Models\UserWalletSpot;
use Internal\Market\Actions\FetchSymbolQuote;

class UpdateSpotWalletUsdt {

    public function __invoke(User $user)
    {
        $quotes = (new FetchSymbolQuote)->getAllSymbol();
        UserWalletSpot::with(['coin'])->where('uid', $user->id)->get()->each(function($item) use ($quotes){
            if (!$item->coin) {
                return true;
            }
            if ($item->coin->id == CoinEnums::DefaultUSDTCoinID) {
                $item->usdt_value = $item->amount;
                $item->save();
                return true;
            }
            $coins = SymbolCoin::find($item->coin_id);
            if (!$coins->symbol) {
                return true;
            }
            $binance = $coins->symbol->binance_symbol ?? '';
            if (!$binance) {
                return true;
            }
            $curQuote = $quotes[strtolower($binance)] ?? 0;
            if ( ! $curQuote) {
                return true;
            }
            $item->usdt_value = bcmul($item->amount, $curQuote, FundsEnums::DecimalPlaces);
            $item->save();
            return true;
        });
        return true;
    }
}

