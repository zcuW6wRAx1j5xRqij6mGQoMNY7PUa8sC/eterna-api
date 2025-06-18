<?php

namespace App\Enums;

class MarketEnums {

    // 交易对最新报价cache key
    const SpotSymbolQuoteCacheKey = 'symbol.spot.quote.%s';
    const FuturesSymbolQuoteCacheKey = 'symbol.futures.quote.%s';

    const FuturesInfluxdbBucket = 'market_futures';
    const SpotInfluxdbBucket = 'market_spot';
}
