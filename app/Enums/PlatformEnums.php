<?php

namespace App\Enums;

class PlatformEnums {
    // 关于我们
    const ProtocolTypeAboutMe = 'about_me';

    // 用户协议
    const ProtocolTypeTermsAndConditions = 'terms_and_conditions';

    // 隐私协议
    const ProtocolTypePrivacyPolicy = 'privacy_policy';

    const ProtocolMaps = [
        self::ProtocolTypeAboutMe,
        self::ProtocolTypeTermsAndConditions,
        self::ProtocolTypePrivacyPolicy,
    ];
}
