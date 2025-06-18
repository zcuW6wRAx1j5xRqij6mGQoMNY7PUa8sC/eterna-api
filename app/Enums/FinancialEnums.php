<?php

namespace App\Enums;

use Amp\Http\Status;

class FinancialEnums{


    // 类型 : 活期
    const CategoryFlexible = 'flexible';
    // 类型 : 定期
    const CategoryFixed = 'fixed';
    
    const CategoryAll = [
        self::CategoryFlexible,
        self::CategoryFixed,
    ];

    // 状态 : 待结算
    const StatusPending = 'pending';

    // 状态 : 已结算
    const StatusSettled = 'settled';

    const StatusAll = [
        self::StatusPending,
        self::StatusSettled,
    ];
}
