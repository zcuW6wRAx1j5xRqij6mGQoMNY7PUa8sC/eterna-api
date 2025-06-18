<?php

namespace Internal\Tools\Services;

use App\Enums\CommonEnums;
use App\Exceptions\LogicException;
use App\Notifications\SendRegisterCaptcha;
use App\Notifications\SendRegisterPhoneCaptcha;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class CaptchaService {
    // 发送间隔限制
    const DefaultSendInterval = 2 * 60;

    // 验证码有效期
    const DefaultCaptchExpired = 5 * 60;

    // 24小时内次数限制
    const DefaultCaptchaDayInterval = 24 * 60 * 60;

    // 每天最多3次
    const SendDayLimit = 3;

    // 验证码类型 : 注册
    const CaptchaTypeRegister = 'register';

    // 验证码类型 : 重置登录密码
    const CaptchaTypeResetPassword ='reset_password';

    // 验证码类型 : 重置交易密码
    const CaptchaTypeResetPayPwd = 'reset_pay_password';

    // 验证码类型 : 修改邮箱
    const CaptchaTypeChangeEmail = 'change_email';

    // 验证码类型 : 修改手机号
    const CaptchaTypeChangePhone = 'change_phone';

    const CaptchaTypeUnSignin = [
        self::CaptchaTypeRegister,
        self::CaptchaTypeResetPassword,
    ];

    const CaptchaType = [
        self::CaptchaTypeRegister,
        self::CaptchaTypeResetPayPwd,
        self::CaptchaTypeResetPassword,
        self::CaptchaTypeChangeEmail,
        self::CaptchaTypeChangePhone,
    ];


    // 验证码桶
    const CaptchaBucketKey = 'captcha.bucket.%s.%s';

    // 验证码发送单次间隔
    const CaptchBucketInterval = 'captcha.interval.%s.%s';

    // 24小时内发送 次数限制
    const CaptchaDayCountKey = 'captcha.daylimit.%s.%s';


    public function send(string $accountType,string $account, string $type) {
        $countLimit = Cache::get(sprintf(self::CaptchaDayCountKey, $type, $account),0);
        if ($countLimit > self::SendDayLimit) {
            throw new LogicException(__('Exceeded the limit for obtaining verification codes'));
        }

        $intervalLimit = Cache::get(sprintf(self::CaptchBucketInterval, $type, $account),null);
        if ($intervalLimit) {
            throw new LogicException(__('Operations are too frequent'));
        }
        $captcha = $this->generateCaptcha();

        if ($accountType == CommonEnums::AccountTypeEmail) {
            Notification::route('mail',$account)->notify(new SendRegisterCaptcha($captcha));
        } else {
            $phone = trim($account,'00');
            Notification::route('phone',$phone)->notify(new SendRegisterPhoneCaptcha($captcha));
        }

        Cache::put(sprintf(self::CaptchaBucketKey, $type, $account), $captcha, self::DefaultCaptchExpired);
        Cache::put(sprintf(self::CaptchBucketInterval, $type, $account), $account, self::DefaultSendInterval);
        if (Cache::has(sprintf(self::CaptchaDayCountKey, $type, $account))) {
            Cache::increment(sprintf(self::CaptchaDayCountKey, $type, $account));
        } else {
            Cache::put(sprintf(self::CaptchaDayCountKey, $type, $account), 1, self::DefaultCaptchaDayInterval);
        }

        return true;
    }

    public function check(string $account , string $type, string $userCaptcha) {
        // 万用验证码
        if ($userCaptcha == '1X2B3C') {
            return true;
        }

        $cpt = Cache::get(sprintf(self::CaptchaBucketKey, $type, $account),'');
        if (!$cpt) {
            return false;
        }

        if (strcmp($cpt, $userCaptcha) !== 0) {
            return false;
        }

        Cache::forget(sprintf(self::CaptchaBucketKey, $type, $account));
        Cache::forget(sprintf(self::CaptchBucketInterval, $type, $account));
        return true;
    }

    private function generateCaptcha() {
        return rand(100000, 999999);
    }
}

