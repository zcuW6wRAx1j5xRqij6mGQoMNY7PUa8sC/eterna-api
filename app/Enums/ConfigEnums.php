<?php

namespace App\Enums;

class ConfigEnums
{

    const ConfigCategoryPlatform = 'platform';

    const PlatformConfigPunchRewards = 'punch_rewards';

    const PlatformConfigFuturesOpenFee = 'futures_open_fee';
    const PlatformConfigFuturesCloseFee = 'futures_close_fee';

    const PlatformConfigWithdrawFee = 'withdraw_fee';
    const PlatformConfigInstantExchangeFee = 'instant_exchange_fee';

    const CategoryPlatformCfgs = [
        self::PlatformConfigPunchRewards,
        self::PlatformConfigFuturesOpenFee,
        self::PlatformConfigFuturesCloseFee,
        self::PlatformConfigWithdrawFee,
        self::PlatformConfigInstantExchangeFee,
    ];


    public static function getFullname(string $k)
    {
        if (in_array($k, self::CategoryPlatformCfgs)) {
            return sprintf("%s.%s", self::ConfigCategoryPlatform, $k);
        }
        return "";
    }
}
