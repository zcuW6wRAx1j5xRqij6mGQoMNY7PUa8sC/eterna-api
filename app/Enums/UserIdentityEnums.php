<?php

namespace App\Enums;

class UserIdentityEnums {

    // 待审核
    const ProcessWaiting = 0;
    // 已通过
    const ProcessPassed = 1;
    // 已拒绝
    const ProcessRejected = 2;

    // 证件类型 : 身份证
    const DocumentTypeIDCard = 'id_card';
    // 证件类型 : 护照
    const DocumentTypePassport = 'passport';
    // 证件类型 : 驾照
    const DocumentTypeDriverLicense = 'driver_license';

    const DocumentTypeMaps = [
        self::DocumentTypeIDCard,
        self::DocumentTypePassport,
        self::DocumentTypeDriverLicense,
    ];
}
