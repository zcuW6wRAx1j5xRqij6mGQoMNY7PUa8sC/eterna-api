<?php

namespace App\Enums;

class FundsEnums{

    const MarginDecimalPlaces = 2;

    // 默认计算小数点位数
    const DecimalPlaces = 8;

    // 特殊小数点, 现货时最多支持的小数点位数
    const SpecialDecimalPlaces = 12;

    // 入金状态 : 等待入金
    const DepositStatusProcessing = 0;
    // 入金状态 : 成功
    const DepositStatusDone = 1;
    // 入金状态 : 失败
    const DepositStatusFailed = 2;

    const DepositStatusMap = [
        self::DepositStatusProcessing,
        self::DepositStatusDone,
        self::DepositStatusFailed,
    ];

    // 出金状态 : 待审核
    const WithdrawStatusProcessing = 0;
    // 出金状态 : 已到账
    const WithdrawStatusDone = 1;
    // 出金状态 : 失败
    const WithdrawStatusFailed = 2;

    const WithdrawStatusMap =[
        self::WithdrawStatusProcessing,
        self::WithdrawStatusDone,
        self::WithdrawStatusFailed,
    ];

    // 待审核
    const AuditStatusProcessWaiting = 0;
    // 已通过
    const AuditStatusProcessPassed = 1;
    // 已拒绝
    const AuditStatusProcessRejected = 2;
    // 失败
    const AuditStatusProcessFailed = 3;


    const AuditStatusMap = [
        self::AuditStatusProcessWaiting,
        self::AuditStatusProcessPassed,
        self::AuditStatusProcessRejected,
        self::AuditStatusProcessFailed
    ];
}
