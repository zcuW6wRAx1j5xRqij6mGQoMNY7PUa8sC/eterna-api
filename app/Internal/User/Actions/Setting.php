<?php

namespace Internal\User\Actions;

use App\Enums\UserLogEnums;
use App\Exceptions\LogicException;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Internal\Tools\Services\CaptchaService;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;
use InvalidArgumentException;

class Setting {

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $name = $request->get('name','');
        $avatar = $request->get('avatar','');
        if ($name) {
            $user->name = $name;
        }
        if ($avatar) {
            $user->avatar = $avatar;
        }
        $user->save();
        return true;
    }

    /**
     * 修改或设置交易密码
     * @param Request $request
     * @return true
     * @throws BadRequestException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     * @throws ConflictingHeadersException
     * @throws InvalidArgumentException
     * @throws InvalidCastException
     */
    public function changeOrSettingTradePassword(Request $request) {
        return DB::transaction(function() use($request){
            $user = $request->user();
            if ($user->trade_password) {
                $old = $request->get('old_trade_password','');
                if (!$old) {
                    throw new LogicException(__('The old password is incorrect'));
                }
                if ( !Hash::check($old, $user->trade_password)) {
                    throw new LogicException(__('The old password is incorrect'));
                }
            }
            $user->trade_password = Hash::make($request->get('trade_password'));
            $user->save();

            $log = new UserLog();
            $log->uid = $user->id;
            $log->log_type = UserLogEnums::LogTypeChangeTradePassword;
            $log->content = [
                'ip'=>$request->ip(),
            ];
            $log->save();
            return true;
        });
    }

    /**
     * 修改密码
     * @param Request $request
     * @return mixed
     */
    public function changePassword(Request $request) {
        return DB::transaction(function() use($request){
            $user = $request->user();
            if ( !Hash::check($request->get('old_password'), $user->password)) {
                throw new LogicException(__('The old password is incorrect'));
            }

            $user->password = Hash::make($request->get('new_password'));
            $user->save();

            $log = new UserLog();
            $log->uid = $user->id;
            $log->log_type = UserLogEnums::LogTypeChangePassword;
            $log->content = [
                'ip'=>$request->ip(),
            ];
            $log->save();
            return true;
        });
    }


    /**
     * 修改邮箱
     * @param Request $request
     * @return void
     * @throws BadRequestException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     * @throws ConflictingHeadersException
     * @throws InvalidArgumentException
     * @throws InvalidCastException
     */
    public function changeEmail(Request $request) {
        return DB::transaction(function() use($request){
            $user = $request->user();
            $newEmail = $request->get('email');

            $exists = User::where('email', $newEmail)->first();
            if ($exists) {
                throw new LogicException(__('The account has been used'));
            }

            if ($user->email) {
                $oldCaptcha = $request->get('old_captcha','');
                if (!$oldCaptcha) {
                    throw new LogicException(__('The old email verification code is incorrect'));
                }
                if ( ! (new CaptchaService)->check($user->email, CaptchaService::CaptchaTypeChangeEmail, $request->get('old_captcha'))) {
                    throw new LogicException(__('The old email verification code is incorrect'));
                }
            }

            if ( ! (new CaptchaService)->check($newEmail, CaptchaService::CaptchaTypeChangeEmail, $request->get('captcha'))) {
                throw new LogicException(__('The new email verification code is incorrect'));
            }


            $user->email = $request->get('email');
            $user->save();

            $log = new UserLog();
            $log->uid = $user->id;
            $log->log_type = UserLogEnums::LogTypeChangeEmail;
            $log->content = [
                'ip'=>$request->ip(),
            ];
            $log->save();

            return true;
        });
    }

    /**
     * 修改手机号
     * @param Request $request
     * @return mixed
     */
    public function changePhone(Request $request) {
        return DB::transaction(function() use($request){
            $user = $request->user();
            $phone = $request->get('phone');
            $phoneCode = $request->get('phone_code');

            $exists = User::where('phone', $phone)->where('phone_code', $phoneCode)->first();
            if ($exists) {
                throw new LogicException(__('The account has been used'));
            }

            if ($user->phone) {
                $oldCaptcha = $request->get('old_captcha','');
                if (!$oldCaptcha) {
                    throw new LogicException(__('The old phone verification code is incorrect'));
                }
                $account = $user->phone_code.$user->phone;
                if ( ! (new CaptchaService)->check($account, CaptchaService::CaptchaTypeChangePhone, $request->get('old_captcha'))) {
                    throw new LogicException(__('The old phone verification code is incorrect'));
                }
            }

            if ( ! (new CaptchaService)->check($phoneCode.$phone, CaptchaService::CaptchaTypeChangeEmail, $request->get('captcha'))) {
                throw new LogicException(__('The new phone verification code is incorrect'));
            }

            $user->phone_code = $request->get('phone_code');
            $user->phone = $request->get('phone');
            $user->save();

            $log = new UserLog();
            $log->uid = $user->id;
            $log->log_type = UserLogEnums::LogTypeChangePhone;
            $log->content = [
                'ip'=>$request->ip(),
            ];
            $log->save();
            return true;
        });
    }
}
