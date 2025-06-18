<?php

namespace Internal\Security\Services;

use App\Exceptions\LogicException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;

class CloudflareCaptcha {

    // 等待响应超时
    const HttpResponseTimeoutSec = 3;

    // 请求验证码校验
    const CaptchaVerifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * 防刷验证码校验
     * @param Request $request
     * @param string $token
     * @return bool
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     * @throws ConflictingHeadersException
     * @throws Exception
     */
    public function __invoke(Request $request, string $token) {
        if ( ! App::environment('prod')) {
            return true;
        }

        $secret= config('cloudflare.bot.secret');
        if (!$secret) {
            Log::error('cloudflare Api Secret 为空');
            throw new LogicException(__('Whoops! Something went wrong'));
        }

        $resp = Http::asForm()
            ->connectTimeout(3)
            ->timeout(self::HttpResponseTimeoutSec)
            ->post(self::CaptchaVerifyUrl,[
                'secret'=>$secret,
                'response'=>$token,
                'remoteip'=>$request->ip(),
        ]);

        if (! $resp->ok()) {
            Log::warning('申请cloudflare验证码失败: status不正确',[
                'resp_body'=>$resp->body(),
                'resp_status'=>$resp->status(),
            ]);
            return false;
        }

        $data = $resp->json();
        $isSuccess = $data['success'] ?? null;
        if ($isSuccess) {
            return true;
        }

        Log::warning('申请cloudflare验证码失败',[
                'resp_body'=>$resp->body(),
                'resp_status'=>$resp->status(),
        ]);
        return false;
    }
}
