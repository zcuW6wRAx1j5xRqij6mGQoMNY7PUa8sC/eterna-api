<?php

namespace App\Enums;

class ThirdpartyEnums {

    const UdunCallbackTypeDeposit = '1';
    const UdunCallbackTypeWithdraw = '2';

    // 审核中
    const UdunCallbacKStatusAuditing = 0;
    // 审核成功
    const UdunCallbacKStatusAuditDone = 1;
    // 审核拒绝
    const UdunCallbacKStatusAuditReject = 2;
    // 交易成功
    const UdunCallbacKStatusTradeSuccess = 3;
    // 交易失败
    const UdunCallbacKStatusTradeFailed = 4;
}
