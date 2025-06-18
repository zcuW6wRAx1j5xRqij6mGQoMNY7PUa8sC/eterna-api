<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\CommonEnums;
use App\Enums\UserLogEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Models\UserLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Internal\Security\Services\CloudflareCaptcha;
use Internal\Tools\Services\CentrifugalService;
use Internal\User\Actions\CreateUser;
use Internal\User\Actions\ForgetPassword;
use Internal\User\Payloads\CreateUserPayload;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;

class AuthController extends ApiController {

    /**
     * 用户登录
     * @param Request $request
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
    public function signin(Request $request) {
        $request->validate([
            'email'=>'nullable|email',
            'account_type'=>['required', Rule::in(CommonEnums::AccountTypeMap)],
            'phone'=>'nullable|numeric',
            'phone_code'=>'nullable|numeric',
            'password'=>'required|string',
        ]);
        $accountType = $request->get('account_type');
        $email = $request->get('email','');
        $phoneCode = $request->get('phone_code','');
        $phone = $request->get('phone','');

        $user = null;

        if ($accountType == CommonEnums::AccountTypeEmail) {
            if (!$email) {
                throw new LogicException(__('Incorrect username or password'));
            }
            $user = User::where('email', $email)->first();
        } else{
            if (!$phone ||!$phoneCode) {
                throw new LogicException(__('Incorrect username or password'));
            }
            $user = User::where('phone_code', $phoneCode)->where('phone', $phone)->first();
        }

        if (!$user) {
            throw new LogicException(__('Incorrect username or password'));
        }

        if ($user->status != CommonEnums::Yes) {
            throw new LogicException(__('Your account is currently unavailable, if you have any questions, please contact customer service'));
        }

        if ( ! Hash::check( $request->get('password'), $user->password)) {
            throw new LogicException(__('Incorrect username or password'));
        }

        $user->latest_login_ip = $request->ip();
        $user->latest_login_time = Carbon::now();
        $user->save();

        $log = new UserLog();
        $log->uid = $user->id;
        $log->log_type = UserLogEnums::LogTypeSigninLog;
        $log->content = [
            'ip'=>$request->ip(),
            'header'=>$request->headers,
        ];
        $log->save();

        return $this->ok([
            'token'=>$user->generateToken(),
            'uid'=>$user->id,
            'ws_token'=>CentrifugalService::getInstance()->generateJWT($user),
            'ws_channel_token'=>CentrifugalService::getInstance()->generateJWT($user, 'person:'.$user->id),
            'expires_at'=> User::DefaultTokenTTL,
        ]);
    }

    /**
     * 注册
     * @param Request $request
     * @param CreateUser $createUser
     * @return JsonResponse
     * @throws BadRequestException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function signup(Request $request, CreateUser $createUser, CloudflareCaptcha $cloudflareCaptcha) {
        $request->validate([
            'account_type'=>['required', Rule::in(CommonEnums::AccountTypeMap)],
            'email'=>'nullable|email',
            'phone'=>'nullable|numeric',
            'phone_code'=>'nullable|string',
            'password'=>['required', Password::min(6)],
            'captcha_code'=>'required|string',
            'invite_code'=>'required|string',
            //'cf_token'=>'nullable|string',
        ]);

        $payload = (new CreateUserPayload)->parseFromRequest($request);
        return $this->ok($createUser($payload));
    }

    /**
     * 忘记登录密码
     * @param Request $request
     * @param ForgetPassword $forgetPassword
     * @return JsonResponse
     * @throws BadRequestException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function forgetSignInPassword(Request $request, ForgetPassword $forgetPassword, CloudflareCaptcha $cloudflareCaptcha) {
        $request->validate([
            'account_type'=>['required', Rule::in(CommonEnums::AccountTypeMap)],
            'email'=>'nullable|email',
            'phone'=>'nullable|numeric',
            'phone_code'=>'nullable|string',
            'captcha_code'=>'required|string',
            'password'=>['required', Password::min(6)],
            //'cf_token'=>'nullable|string',
        ]);
        // if ( ! $cloudflareCaptcha($request, $request->get('cf_token'))) {
        //     throw new LogicException(__('Whoops! Something went wrong'));
        // }
        return $this->ok($forgetPassword($request));
    }
}
