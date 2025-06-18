<?php

namespace Internal\Wallet\Actions;

use App\Models\User;
use App\Models\UserWalletFutures;

class FuturesWallet {

    public function __invoke(User $user)
    {
        return UserWalletFutures::where('uid', $user->id)->first();
    }
}
