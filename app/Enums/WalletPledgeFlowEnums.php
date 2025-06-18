<?php

namespace App\Enums;

class WalletPledgeFlowEnums {

    // 待审核
    const PledgeFlowStatusPending = 'pending';
    // 已拒绝
    const PledgeFlowStatusRejected = 'rejected';
    // 质押中
    const PledgeFlowStatusHold = 'hold';
    // 结单进行中
    const PledgeFlowStatusClosing = 'closing';
    // 已结单
    const PledgeFlowStatusClosed = 'closed';

    const Maps = [
        self::PledgeFlowStatusPending   => 'pending',
        self::PledgeFlowStatusRejected  => 'rejected',
        self::PledgeFlowStatusHold      => 'hold',
        self::PledgeFlowStatusClosing   => 'closing',
        self::PledgeFlowStatusClosed    => 'closed'
    ];

}
