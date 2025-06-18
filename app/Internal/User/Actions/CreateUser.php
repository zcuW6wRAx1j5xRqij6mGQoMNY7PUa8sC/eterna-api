<?php

namespace Internal\User\Actions;

use App\Enums\CommonEnums;
use App\Events\UserCreated;
use App\Exceptions\LogicException;
use App\Models\AdminUser;
use App\Models\User;
use App\Models\UserLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Internal\Tools\Services\CaptchaService;
use Internal\User\Payloads\CreateUserPayload;

class CreateUser {

    public function __invoke(CreateUserPayload $payload)
    {
        return DB::transaction(function() use($payload){
            $user = new User();
            if ($payload->accountType == CommonEnums::AccountTypeEmail) {
                $existsCheck = User::where('email', $payload->email)->first();
                $user->email = $payload->email;
                $username = $payload->email;
            } else {
                $existsCheck = User::where('phone', $payload->phone)->where('phone_code', $payload->phoneCode)->first();
                $user->phone_code = $payload->phoneCode;
                $user->phone = $payload->phone;
                $username = $payload->phoneCode.$payload->phone;
            }

            if ($existsCheck) {
                throw new LogicException(__('The account has been used'));
            }

            // 校验验证码
            if (! $payload->isAdmin) {
                if ( ! (new CaptchaService)->check($username, CaptchaService::CaptchaTypeRegister, $payload->captcha)) {
                    throw new LogicException(__('Incorrect verification code'));
                }
                $invitee = AdminUser::where('invite_code', $payload->inviteCode)->value('id');
                if (!$invitee) {
                    throw new LogicException(__('Incorrect invite code'));
                }
                $payload->salesman = $invitee;
            }

//            $code = [
//                'HM336', // 黑马
//                'ZS665', // 左手
//                'TM886', // 唐明
//                'XF668', // 小风
//                '00001', // 管理后台新增
//            ];


            if ($payload->isAdmin) {
                $user->role_type = CommonEnums::RoleTypeInternal;
            }
            $user->level_id         = UserLevel::getFirstLevel();
            $user->name             = 'user:'.Str::random(8);
            $user->password         = Hash::make($payload->password);
            $user->register_ip      = $payload->ip;
            $user->register_device  = $payload->device;
            $user->parent_id        = $payload->inviteCode;
            $user->salesman         = $payload->salesman;

            $user->save();

            // 创建钱包
            UserCreated::dispatch($user);
            return true;
        });
    }
}

