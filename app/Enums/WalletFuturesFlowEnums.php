<?php

namespace App\Enums;

class WalletFuturesFlowEnums {

    // 系统操作
    const FlowSystem = 'system';
    // 资金划转 入
    const FlowTransferIn = 'transfer_in';
    // 资金划转 出
    const FlowTransferOut = 'transfer_out';

    // 建仓保证金
    const FlowPositionMargin = 'postion_margin';

    // 增加保证金
    const FlowIncreaseMargin = 'increase_margin';
    // 减少保证金
    const FlowReduceMargin = 'reduce_margin';

    // 平仓流水
    const FlowPositionClose = 'position_close';

    // 建仓手续费
    const FlowPostionFee = 'position_fee';

    // 挂单
    const FlowTypePostingOrder = 'posting_order';

    // 挂单取消退回
    const FlowTypeRefundPostingOrder = 'refund_posting_order';

    const Maps = [
        self::FlowTransferIn,
        self::FlowTransferOut,
        self::FlowPositionMargin,
        self::FlowPositionClose,
        self::FlowPostionFee,
        self::FlowSystem,
        self::FlowPositionClose,
        self::FlowTypeRefundPostingOrder,
        self::FlowIncreaseMargin,
        self::FlowReduceMargin,
    ];

}
