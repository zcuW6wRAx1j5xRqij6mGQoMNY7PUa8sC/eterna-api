<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\ConfigEnums;
use App\Enums\UserIdentityEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\UserPunchLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Internal\Common\Services\ConfigService;
use Internal\Security\Services\CloudflareCaptcha;
use Internal\User\Actions\Inboxs;
use Internal\User\Actions\Profile;
use Internal\User\Actions\Setting;
use Internal\User\Actions\SubmitIdentity;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;

/** @package App\Http\Controllers\Api\App */
class UserController extends ApiController
{

    /**
     * 用户个人信息
     * @param Request $request
     * @param Profile $profile
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function profile(Request $request, Profile $profile)
    {
        return $this->ok($profile($request));
    }

    /**
     * 个人信息设置
     * @param Request $request
     * @param Setting $setting
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function setting(Request $request, Setting $setting)
    {
        $request->validate([
            'name' => 'nullable|string',
            'avatar' => 'nullable|string',
        ]);
        $setting($request);
        return $this->ok(true);
    }

    /**
     * 修改交易密码
     * @param Request $request
     * @param Setting $setting
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function changePassword(Request $request, Setting $setting)
    {
        $request->validate([
            'old_password' => 'required|string',
            'new_password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        $setting->changePassword($request);
        return $this->ok(true);
    }


    /**
     * 修改交易密码
     * @param Request $request
     * @param Setting $setting
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     * @throws ConflictingHeadersException
     * @throws InvalidArgumentException
     * @throws InvalidCastException
     */
    public function changeTradePassword(Request $request, Setting $setting)
    {
        $request->validate([
            'old_trade_password' => 'numeric',
            'trade_password' => ['required', 'numeric', Password::min(6)->max(6)]
        ]);
        $setting->changeOrSettingTradePassword($request);
        return $this->ok(true);
    }

    /**
     * 修改电子邮箱
     * @param Request $request
     * @param Setting $setting
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     * @throws ConflictingHeadersException
     * @throws InvalidArgumentException
     * @throws InvalidCastException
     */
    public function changeEmail(Request $request, Setting $setting, CloudflareCaptcha $cloudflareCaptcha)
    {
        $request->validate([
            'email' => 'required|email',
            'old_captcha' => 'string',
            'captcha' => 'required|string',
            // 'cf_token' => 'nullable|string',
        ]);

        // if (! $cloudflareCaptcha($request, $request->get('cf_token'))) {
        //     throw new LogicException(__('Whoops! Something went wrong'));
        // }

        $setting->changeEmail($request);
        return $this->ok(true);
    }

    /**
     * 修改手机号
     * @param Request $request
     * @param Setting $setting
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function changePhone(Request $request, Setting $setting, CloudflareCaptcha $cloudflareCaptcha)
    {
        $request->validate([
            'phone' => 'required|numeric',
            'phone_code' => 'required|string',
            'old_captcha' => 'string',
            'captcha' => 'required|string',
            // 'cf_token' => 'nullable|string',
        ]);

        // if (! $cloudflareCaptcha($request, $request->get('cf_token'))) {
        //     throw new LogicException(__('Whoops! Something went wrong'));
        // }

        $setting->changePhone($request);
        return $this->ok(true);
    }

    /**
     * 提交实名审核
     * @param Request $request
     * @param SubmitIdentity $submitIdentity
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function submitIdentity(Request $request, SubmitIdentity $submitIdentity)
    {
        $request->validate([
            'document_type' => ['required', Rule::in(UserIdentityEnums::DocumentTypeMaps)],
            'country_id' => 'required|numeric',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'document_number' => 'required|string',
            'face' => 'required|string',
            'document_frontend' => 'required|string',
            'document_backend' => 'string',
        ]);
        $submitIdentity($request);
        return $this->ok(true);
    }

    /**
     * 实名认证信息进度
     * @param Request $request
     * @param Profile $profile
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function showIdentityProcess(Request $request, Profile $profile)
    {
        $data = $profile->indentityStatus($request);
        return $this->ok($data);
    }



    /**
     * 收件箱
     * @param Request $request
     * @param Inboxs $inboxs
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function inbox(Request $request, Inboxs $inboxs)
    {
        $request->validate([
            'page' => 'numeric',
            'page_size' => 'numeric',
        ]);
        $data = $inboxs($request);
        return $this->ok(listResp($data));
    }

    /**
     * 消息详情
     * @param Request $request
     * @param Inboxs $inboxs
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function msgDetail(Request $request, Inboxs $inboxs)
    {
        $request->validate([
            'id' => 'required|numeric',
        ]);
        return $this->ok($inboxs->detail($request));
    }

    /**
     * 签到
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     * @throws InvalidArgumentException
     * @throws InvalidCastException
     */
    public function punch(Request $request)
    {
        DB::transaction(function () use ($request) {
            $today = UserPunchLog::where('uid', $request->user()->id)->where('punch_date', Carbon::now()->toDateString())->first();
            if ($today) {
                return $this->ok(false);
            }
            $rewards = ConfigService::getIns()->fetch(ConfigEnums::PlatformConfigPunchRewards, 0);
            $user = $request->user();
            $user->punch_rewards = $user->punch_rewards + $rewards;
            $user->save();

            $log = new UserPunchLog();
            $log->uid = $request->user()->id;
            $log->punch_date = Carbon::now()->toDateString();
            $log->rewards = $rewards;
            $log->save();
            return true;
        });

        return $this->ok(true);
    }
}
