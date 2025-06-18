<?php

namespace App\Enums;

class IEOEnums {

    // 未开始
    const StatusWaiting = '0';
    // 认购中
    const StatusPending = '1';
    // 抽签中
    const StatusProcessing = '2';
    // 已结束
    const StatusCompleted = '3';

    const Status = [
        self::StatusWaiting,
        self::StatusPending,
        self::StatusProcessing,
        self::StatusCompleted
    ];

    // 订单状态 : 已申请
    const OrderStatusOrder = '0';
    // 订单状态 : 进行中
    const OrderStatusProcessing = '1';
    // 订单状态 : 已完成
    const OrderStatusCompleted = '2';
    // 订单状态 : 已取消
    const OrderStatusFailed = '3';

    const OrderStatus = [
        self::OrderStatusOrder,
        self::OrderStatusProcessing,
        self::OrderStatusCompleted,
        self::OrderStatusFailed,
    ];

    // 用户订单查询状态 : 申请
    const OrderStatusQueryPending = 'pending';
    // 用户订单查询状态 : 进行中
    const OrderStatusQueryProcessing = 'processing';
    // 用户订单查询状态 : 已结束
    const OrderStatusQueryCompleted = 'completed';

    const OrderStatusQueryAll = [
        self::OrderStatusQueryPending,
        self::OrderStatusQueryProcessing,
        self::OrderStatusQueryCompleted,
    ];

    public static function translateStatus(string $queryStatus) {
        switch($queryStatus) {
            case self::OrderStatusQueryPending:
                return [self::OrderStatusOrder];
                break;
            case self::OrderStatusQueryProcessing:
                return [self::OrderStatusProcessing];
                break;
            case self::OrderStatusQueryCompleted:
                return [
                    self::OrderStatusCompleted,
                    self::OrderStatusFailed 
                ];
                break;
        }
        return "";
    }
}
