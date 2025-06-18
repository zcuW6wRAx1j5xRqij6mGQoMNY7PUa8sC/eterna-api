<?php

namespace Internal\Wallet\Actions;

use App\Enums\CoinEnums;
use App\Http\Resources\UserWalletSpotCollection;
use App\Models\User;
use App\Models\UserWalletSpot;

class SpotWallet {

    public function __invoke(User $user)
    {
        (New UpdateSpotWalletUsdt)($user);
        $coins = UserWalletSpot::with(['coin'])->where('uid', $user->id)->get();
        $usdtWallet = UserWalletSpot::where('uid', $user->id)->where('coin_id', CoinEnums::DefaultUSDTCoinID)->first();
        // $total = $coins->sum('usdt_value');
        return [
            'total'=> $usdtWallet->amount ?? 0,
            'coins'=> new UserWalletSpotCollection($coins),
        ];
    }

}

