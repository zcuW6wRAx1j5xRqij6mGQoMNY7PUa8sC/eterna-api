<?php

namespace App\Enums;

class OrderEnums {
    // 默认 1手 = 100 USDT 交易额
    const DefaultLotsTradevalue = 100;

    // 最小保证金率 (维持保证金率)
    const MinMarginRatio = 0.05;

    const SideSell = 'sell'; // 卖
    const SideBuy  = 'buy'; // 买

    const SideMap = [
        self::SideBuy,
        self::SideSell
    ];

    // 交易类型 : 限价
    const TradeTypeLimit = 'limit';
    // 交易类型 : 市价
    const TradeTypeMarket = 'market';

    const TradeTypeMap = [
        self::TradeTypeLimit,
        self::TradeTypeMarket,
    ];

    // 保证金类型 : 逐仓
    const MarginTypeIsolated = 'isolated';
    // 保证金类型 : 全仓
    const MarginTypeCrossed = 'crossed';

    const MarginTypeMap = [
        self::MarginTypeIsolated,
        self::MarginTypeCrossed,
    ];

    // 合约持仓监控key
    const DerivativeOrderMonitorKey = 'symbol.position.%s';
    // 合约某个用户的所有仓位信息
    const FuturesUserPotionsKey = 'futures.crossed.position.uid.%d';

    const SpotTradeStatusProcessing = 'pending'; // 挂单中
    const SpotTradeStatusDone = 'success'; // 成交
    const SpotTradeStatusFailed = 'failed'; // 失败

    const SpotTradeStatusMap = [
        self::SpotTradeStatusProcessing,
        self::SpotTradeStatusDone,
        self::SpotTradeStatusFailed,
    ];

    // 待审核
    const PledgeTradeStatusPending = 'pending';
    // 已拒绝
    const PledgeTradeStatusRejected = 'rejected';
    // 质押中
    const PledgeTradeStatusHold = 'hold';
    // 结单进行中
    const PledgeTradeStatusClosing = 'closing';
    // 已结单
    const PledgeTradeStatusClosed = 'closed';

    const PledgeTradeStatusMap = [
        self::PledgeTradeStatusPending,
        self::PledgeTradeStatusRejected,
        self::PledgeTradeStatusHold,
        self::PledgeTradeStatusClosing,
        self::PledgeTradeStatusClosed,
    ];


    const FuturesTradeStatusCancel = 'cancel'; // 挂单取消
    const FuturesTradeStatusProcessing = 'pending'; // 挂单中
    const FuturesTradeStatusOpen = 'open'; // 持仓中
    const FuturesTradeStatusClosing = 'closing'; // 平仓中
    const FuturesTradeStatusClosed = 'closed'; // 已平仓

    const CheckAllowChangeMarginTypeStatus = [
        self::FuturesTradeStatusProcessing,
        self::FuturesTradeStatusOpen,
        self::FuturesTradeStatusClosing,
    ];

    const FuturesTradeStatusMap = [
        'all',
        self::FuturesTradeStatusCancel,
        self::FuturesTradeStatusProcessing,
        self::FuturesTradeStatusOpen,
        self::FuturesTradeStatusClosing,
        self::FuturesTradeStatusClosed
    ];


    const TradeTypeBuy = 'buy';
    const TradeTypeSell = 'sell';

    const CommonTradeTypeMap = [
        self::TradeTypeBuy,
        self::TradeTypeSell,
    ];

    // 普通平仓
    const FuturesCloseTypeNormal = 'normal';
    // SL 平仓
    const FuturesCloseTypeSL = 'sl';
    // TP 平仓
    const FuturesCloseTypeTP = 'tp';
    // 强制平仓
    const FuturesCloseTypeForces = 'force';

    const FuturesCloseTypeAll = [
        self::FuturesCloseTypeNormal,
        self::FuturesCloseTypeSL,
        self::FuturesCloseTypeTP,
        self::FuturesCloseTypeForces,
    ];

    // 默认杠杆列表
    const DefaultLeverageMap = [
        25, 50 ,75 ,100
    ];

    // 现货挂单-买单订单池 key
    const SpotBuyLimitOrderKey = "spot.orders.limit.%s.buy";
    // 现货挂单-卖单订单池 key
    const SpotSellLimitOrderKey = "spot.orders.limit.%s.sell";

    // 合约挂单-买单订单池 key
    const FuturesBuyLimitOrders = "futures.orders.limit.%s.buy";
    // 合约挂单-卖单订单池 key
    const FuturesSellLimitOrders = "futures.orders.limit.%s.sell";

    // 现货 控盘价格 key
    const SpotFakePriceKey = "spot.fake.price.%s";
    // 合约 控盘价格 key
    const FuturesFakePriceKey = "futures.fake.price.%s";




    const TradeStatusPending = 'pending';
    const TradeStatusRejected = 'rejected';
    const TradeStatusAccepted = 'accepted';

    const TradeStatusMap = [
        self::TradeStatusPending,
        self::TradeStatusRejected,
        self::TradeStatusAccepted,
    ];

    const StatusNormal = 1;
    const StatusAbnormal = 2;
    const IntegerStatus = [
        self::StatusNormal,
        self::StatusAbnormal,
    ];


}
