<?php

namespace App\Enums;

class IntervalEnums {
    //const Interval1Second = '1s';

    const Interval1Minute = '1m';
    //const Interval3Minutes = '3m';
    const Interval5Minutes = '5m';
    const Interval15Minutes = '15m';
    const Interval30Minutes = '30m';

    const Interval1Hour = '1h';
    //const Interval2Hours = '2h';
    //const Interval4Hours = '4h';
    //const Interval6Hours = '6h';
    //const Interval8Hours = '8h';
    //const Interval12Hours = '12h';

    const Interval1Day = '1d';
   //const Interval3Days = '3d';

    const Interval1Week = '1w';

    const Interval1Month = '1M';

    // 特殊时间端 : 1M
    const SpecialInterval1Month = '1mo';

    // 支持的k线时间段
    const AllMaps = [
        self::Interval1Minute,
        self::Interval5Minutes,
        self::Interval15Minutes,
        self::Interval30Minutes,
        self::Interval1Hour,
        self::Interval1Day,
        self::Interval1Week,
        self::Interval1Month,
    ];
}
