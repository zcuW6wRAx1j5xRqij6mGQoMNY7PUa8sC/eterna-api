<?php

namespace App\Enums;

class CommonEnums {

    const No = 0;
    const Yes = 1;

    const Paginate = 20;

    const DefaultLanguage = self::LangZh;


    const LangZh = 'zh-CN'; // 简体中文
    const LangEn = 'en-US'; // 英语
    const LangZhTw = 'zh-TW'; // 简体繁文
    const LangES = 'es-ES'; // 西班牙
    const LangFR = 'fr-FR'; // 法语
    const LangIt = 'it-IT'; // 意大利
    const LangDe = 'de-DE'; // 德语

    const Langs = [
        self::LangZh,
        self::LangEn,
        self::LangZhTw,
        self::LangES,
        self::LangFR,
        self::LangIt,
        self::LangDe
    ];

    const AccountTypeEmail = 'email';
    const AccountTypePhone = 'phone';

    const AccountTypeMap = [
        self::AccountTypeEmail,
        self::AccountTypePhone,
    ];

    const PlatformIOS = 'ios';
    const PlatformAndroid = 'android';

    const PlatformAll = [
        self::PlatformIOS,
        self::PlatformAndroid,
    ];

    // 引擎回掉类型 : 挂单处理
    const EngineTaskLimitOrder = 'limit_order';
    // 引擎回掉类型 : 平仓处理
    const EngineTaskClosePosition = 'close_position';

    const EngineTaskAll = [
        self::EngineTaskLimitOrder,
        self::EngineTaskClosePosition
    ];

    // 普通用户
    const RoleTypeNormal = 1;
    // 内部用户
    const RoleTypeInternal = 2;
    // 测试用户
    const RoleTypeTest = 3;

    const RoleTypeAll = [
        self::RoleTypeNormal,
        self::RoleTypeInternal,
        self::RoleTypeTest,
    ];

    const LanguageZhCN = 'zh_CN';
    const LanguageZhTW = 'zh_TW';
    const LanguageEn = 'en';

    const LanguageDe = 'de';
    const LanguageEs = 'es';

    const LanguageFr = 'fr';

    const LanguageIt = 'it';

    const LanguageAll = [
        self::LanguageZhCN,
        self::LanguageZhTW,
        self::LanguageEn,
        self::LanguageDe,
        self::LanguageEs,
        self::LanguageFr,
        self::LanguageIt,
    ];

    const USDCCoinID = 25;
    const PledgeUSDNum = 500;

    const CommandSalesman = 1;
    const salesmanRoleIdCollect = [
        7,
    ];
    const salesmanRoleId = 3;
    const salesmanLeaderRoleId = 2;
}
