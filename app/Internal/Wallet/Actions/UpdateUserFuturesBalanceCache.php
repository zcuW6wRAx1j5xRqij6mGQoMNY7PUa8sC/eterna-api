<?php

namespace Internal\Wallet\Actions;

class UpdateUserFuturesBalanceCache {

    const CacheKey = 'user.account.%d';

    public function __invoke(int $uid, string $latestBalance)
    {
        RedisMarket()->set(sprintf(self::CacheKey, $uid), $latestBalance);
        return true;
    }
}

