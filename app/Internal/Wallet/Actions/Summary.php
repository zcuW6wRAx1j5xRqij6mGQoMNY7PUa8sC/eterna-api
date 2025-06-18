<?php

namespace Internal\Wallet\Actions;

use App\Enums\FundsEnums;
use App\Models\User;
use App\Models\UserWalletFutures;
use App\Models\UserWalletFuturesSummary;
use App\Models\UserWalletSpot;
use Illuminate\Support\Carbon;

class Summary {

    public function __invoke(User $user)
    {
        (new UpdateSpotWalletUsdt)($user);

        $spot = UserWalletSpot::where('uid', $user->id)->sum('usdt_value');
        $futures = UserWalletFutures::where('uid', $user->id)->first();


        $todaySummary =  UserWalletFuturesSummary::where('uid', $user->id)->where('summary_date', Carbon::now()->toDateString())->first();

        $total = bcadd(
            $spot,
            bcadd(
                $futures->balance,
                $futures->lock_balance,
                FundsEnums::DecimalPlaces
            ),
            FundsEnums::DecimalPlaces
        );

        $avaiable = bcadd($spot, $futures->balance, FundsEnums::DecimalPlaces);
        return [
            'total'=>$total,
            'spot'=>$spot,
            'futures'=>$futures->balance,
            'avaiable'=>$avaiable,
            'total_profit'=>$todaySummary->total_profit ?? 0,
        ];
    }
}

