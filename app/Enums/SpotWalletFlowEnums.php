<?php

namespace App\Enums;

class SpotWalletFlowEnums {

    // 系统操作
    const FlowTypeSystem = 'system';

    // 系统操作 : 充值
    const FlowTypeSystemDeposit = 'system_deposit';
    // 系统操作 : 提现
    const FlowTypeSystemWithdraw = 'system_withdraw';

    // 提现
    const FlowTypeWithdraw = 'withdraw';

    // 提现手续费
    const FlowTypeWithdrawFee = 'withdraw_fee';

    // 提现手续费 返还
    const FlowTypeWithdrawFeeRefund = 'withdraw_fee_refund';

    // 提现失败返还
    const FlowTypeWithdrawRefund = 'withdraw_refund';

    // 入金
    const FlowTypeDeposit = 'deposit';

    // 转入
    const FlowTypeTransferIn = 'transfer_in';

    // 转出
    const FlowTypeTransferOut = 'transfer_out';

    // 挂单
    const FlowTypePostingOrder = 'posting_order';

    // 挂单取消退回
    const FlowTypeRefundPostingOrder = 'refund_posting_order';
    //闪兑扣除
    const FlowTypeInstantExchangeDeduct = 'instant_exchange_deduct';
    //闪兑增加
    const FlowTypeInstantExchangeAdd = 'instant_exchange_add';

    // 成交
    const FlowTypeExecution = 'execution';
    // IEO 购买
    const FlowTypeIEO = 'buy_ieo';
    // IEO 退回
    const FlowTypeIEORefund = 'buy_ieo_refund';
    //IEO 清算
    const FlowTypeIEOSettlement = 'buy_ieo_settlement';
    // 红利
    const FlowTypeDividend = 'dividend';

    // 买入理财
    const FlowTypeFinancial = 'financial';
    // 理财结算
    const FlowTypeFinancialSettle = 'financial_settle';

    const Maps = [
        self::FlowTypeWithdraw,
        self::FlowTypeDeposit,
        self::FlowTypeTransferIn,
        self::FlowTypeTransferOut,
        self::FlowTypePostingOrder,
        self::FlowTypeRefundPostingOrder,
        self::FlowTypeExecution,
        self::FlowTypeSystem,
        self::FlowTypeIEO,
        self::FlowTypeIEOSettlement,
        self::FlowTypeIEORefund,
        self::FlowTypeFinancial,
        self::FlowTypeFinancialSettle,
        self::FlowTypeInstantExchangeDeduct,
    ];

}
