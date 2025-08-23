<?php

namespace Internal\Wallet\Actions;

use App\Enums\CoinEnums;
use App\Http\Resources\UserWalletSpotCollection;
use App\Models\SymbolCoin;
use App\Models\User;
use App\Models\UserWalletSpot;

class SpotWallet {

    public function __invoke(User $user)
    {
        (New UpdateSpotWalletUsdt)($user);
        $coins = UserWalletSpot::with(['coin'])->where('uid', $user->id)->get();
        $usdtWallet = UserWalletSpot::where('uid', $user->id)->sum('usdt_value');
        // $total = $coins->sum('usdt_value');
        return [
            'total'=> $usdtWallet ?? 0,
            'coins'=> new UserWalletSpotCollection($coins),
        ];
    }

    public function selector(User $user)
    {
        (New UpdateSpotWalletUsdt)($user);
        $coins  = UserWalletSpot::query()->where('uid', $user->id)
            ->orderByDesc('usdt_value')
            ->pluck('amount', 'coin_id')
            ->toArray();
        $rows   = SymbolCoin::query()->join('platform_wallet', 'platform_wallet.coin_id', '=', 'symbol_coins.id','left')
            ->select('symbol_coins.id AS coin_id','symbol_coins.logo','symbol_coins.name')
            ->groupBy('symbol_coins.id')
            ->orderBy('symbol_coins.sort')
            ->get();
        foreach ($rows as $row) {
            $exists                 = array_key_exists($row->coin_id, $coins);
            $coins[$row->coin_id]   = [
                'coin_id'       => $row->coin_id,
                'coin_name'     => $row->name,
                'logo'          => $row->logo,
                'amount'        => $exists?$coins[$row->coin_id]:'0.00000000',
            ];
        }

        return array_values($coins);
    }
}

