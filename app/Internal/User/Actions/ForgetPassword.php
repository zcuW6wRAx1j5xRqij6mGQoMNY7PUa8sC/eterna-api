<?php

namespace Internal\User\Actions;

use App\Enums\CommonEnums;
use App\Enums\UserLogEnums;
use App\Events\UserChangePassword;
use App\Exceptions\LogicException;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Internal\Tools\Services\CaptchaService;

class ForgetPassword
{

    public function __invoke(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $user = null;

            $username = null;
            $accountType = $request->get('account_type');
            $email = $request->get('email', '');
            $phoneCode = $request->get('phone_code', '');
            $phone = $request->get('phone', '');
            $password = $request->get('password', '');
            $captcha = $request->get('captcha_code', '');

            if ($accountType == CommonEnums::AccountTypeEmail) {
                if (!$email) {
                    throw new LogicException(__('Incorrect submitted data'));
                }
                $user = User::where('email', $email)->first();
                $username = $email;
            } else {
                if (!$phone || !$phoneCode) {
                    throw new LogicException(__('Incorrect submitted data'));
                }
                $user = User::where('phone', $phone)->where('phone_code', $phoneCode)->first();
                $username = $phoneCode . $phone;
            }

            if (!$user) {
                throw new LogicException(__('Incorrect account'));
            }

            // 校验验证码
            if (! (new CaptchaService)->check($username, CaptchaService::CaptchaTypeResetPassword, $captcha)) {
                throw new LogicException(__('Incorrect verification code'));
            }
            $user->password = Hash::make($password);
            $user->save();

            $log = new UserLog();
            $log->uid = $user->id;
            $log->log_type = UserLogEnums::LogTypeChangePassword;
            $log->content = [
                'ip' => $request->ip(),
                'is_admin' => false,
            ];
            $log->save();

            UserChangePassword::dispatch($user);
            return true;
        });
    }
}
