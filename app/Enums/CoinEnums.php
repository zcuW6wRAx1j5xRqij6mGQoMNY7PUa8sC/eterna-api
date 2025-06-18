<?php

namespace App\Enums;

class CoinEnums {

    // 默认USDT 货币ID
    // const DefaultUSDTCoinID = 1;

    // 默认USDC 货币ID
    const DefaultUSDTCoinID = 25;

    const USDTTRC20 = 'USDT-TRC20';
    const USDTERC20 = 'USDT-ERC20';
    const USDTCoins = [
        self::USDTTRC20,
        self::USDTERC20,
    ];
}
