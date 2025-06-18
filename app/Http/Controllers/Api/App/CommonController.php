<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\CommonEnums;
use App\Enums\OrderEnums;
use App\Enums\PhoneCodeEnums;
use App\Enums\PlatformEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Jobs\HandelEngineClosePositionCallabck;
use App\Jobs\HandleEngineLimitOrderCallback;
use App\Jobs\ReceiveClosePosition;
use App\Jobs\SendRefreshOrder;
use App\Models\PlatformAnnouncement;
use App\Models\PlatformAnnouncementReadLog;
use App\Models\PlatformCountry;
use App\Models\PlatformNews;
use App\Models\PlatformProtocol;
use App\Models\PlatformVersion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Exception;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Internal\Common\Actions\Banners;
use Internal\Common\Actions\Notices;
use Internal\Common\Services\R2Service;
use Internal\Security\Services\CloudflareCaptcha;
use Internal\Tools\Services\CaptchaService;
use InvalidArgumentException;
use LogicException as GlobalLogicException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;

/** @package App\Http\Controllers\Api\App */
class CommonController extends ApiController {

    public function phoneCode(Request $request) {
        return $this->ok(PhoneCodeEnums::Maps);
    }

    public function banners(Request $request, Banners $banners) {
        return $this->ok($banners($request));
    }

    public function notices(Request $request, Notices $notices) {
        return $this->ok($notices($request));
    }

    /**
     * 平台公告
     * @param Request $request 
     * @return JsonResponse 
     * @throws BindingResolutionException 
     */
    public function announcement(Request $request) {
        $list = PlatformAnnouncement::where('status', CommonEnums::Yes)->get();
        if ($list->isEmpty()) {
            return $this->ok([]);
        }
        $user = $request->user();
        $data = [];
        $list->each(function ($item) use (&$data, $user) {
            $read = PlatformAnnouncementReadLog::where('announcement_id', $item->id)->where('user_id', $user->id)->first();
            if (!$read) {
                $data[] = $item->toArray();
            }
            return true;
        });
        return $this->ok($data);
    }

    /**
     * 读取公告
     * @param Request $request 
     * @return JsonResponse 
     * @throws BadRequestException 
     * @throws InvalidArgumentException 
     * @throws BindingResolutionException 
     * @throws InvalidCastException 
     */
    public function readTagAnnouncement(Request $request) {
        $request->validate([
            'announcement_id'=>'required|numeric',
        ]);
        $user = $request->user();
        $announcementId = $request->get('announcement_id');
        if (PlatformAnnouncementReadLog::query()->where('announcement_id', $announcementId)->where('user_id', $user->id)->exists()) {
            return $this->ok(true);
        }

        $log = new PlatformAnnouncementReadLog;
        $log->announcement_id = $announcementId;
        $log->user_id = $user->id;
        $log->save();
        return $this->ok(true);
    }

    /**
     * 国家列表
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function countryList(Request $request) {
        $data = PlatformCountry::where('status', CommonEnums::Yes)->get();
        return $this->ok($data);
    }

    public function noticeDetail(Request $request, Notices $notices) {
        $request->validate([
            'id'=>'required|numeric',
        ]);
        return $this->ok($notices->detail($request));
    }

    /**
     * 图片访问地址
     *
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function imagesUrl(Request $request) {
        $path = (new R2Service)->publicPath();
        return $this->ok($path);
    }

    /**
     * 新闻列表
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function news(Request $request) {
        $res = PlatformNews::select(['id','cover','title','created_at'])->where('status',CommonEnums::Yes)->orderByDesc('created_at')
            ->paginate($request->get('page_size',15),['*'],null, $request->get('page',1));
        return $this->ok(listResp($res));
    }

    /**
     * 新闻详情
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function newsDetail(Request $request) {
        $newsId = $request->get('news_id');
        $news = PlatformNews::find($newsId);
        return $this->ok($news);
    }

    /**
     * 获取验证码
     * @param Request $request
     * @param CaptchaService $captchaService
     * @param CloudflareCaptcha $cloudflareCaptcha
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     * @throws ConflictingHeadersException
     * @throws Exception
     */
    public function getCatpchaUnSignin(Request $request, CaptchaService $captchaService, CloudflareCaptcha $cloudflareCaptcha) {
        $request->validate([
            'account_type'=>['required', Rule::in(CommonEnums::AccountTypeMap)],
            'email'=>'nullable|email',
            'type'=>['required', Rule::in(CaptchaService::CaptchaTypeUnSignin)],
            'phone_code'=>'nullable|numeric',
            'phone'=>'nullable|numeric',
            'cf_token'=>'nullable|string'
        ]);

        $accountType = $request->get('account_type');
        $email = $request->get('email','');
        $phoneCode = $request->get('phone_code','');
        $phone = $request->get('phone','');

        $account = '';
        if ($accountType == CommonEnums::AccountTypeEmail) {
            if (!$email) {
                throw new LogicException(__('Incorrect submitted data'));
            }
            $account = $email;
        } else {
            if (!$phoneCode || !$phone) {
                throw new LogicException(__('Incorrect submitted data'));
            }
            $account = $phoneCode.$phone;
        }

        if ( ! $cloudflareCaptcha($request, $request->get('cf_token'))) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }

        $type = $request->get('type');

        $exists = $accountType == CommonEnums::AccountTypeEmail ? User::where('email', $email)->first() : User::where('phone_code', $phoneCode)->where('phone', $phone)->first();

        if ($type == CaptchaService::CaptchaTypeRegister) {
            if ($exists) {
                throw new LogicException(__('The account has been used'));
            }
        } else {
            if (!$exists) {
                throw new LogicException(__('Incorrect Account Data'));
            }
        }
        $captchaService->send($accountType,$account, $request->get('type'));
        return $this->ok(true);
    }

    /**
     * 获取验证码 , 登录后
     * @param Request $request
     * @param CaptchaService $captchaService
     * @param CloudflareCaptcha $cloudflareCaptcha
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     * @throws ConflictingHeadersException
     * @throws Exception
     */
    public function getCatpcha(Request $request, CaptchaService $captchaService, CloudflareCaptcha $cloudflareCaptcha) {
        $request->validate([
            'account_type'=>['required', Rule::in(CommonEnums::AccountTypeMap)],
            'email'=>'nullable|email',
            'type'=>['required', Rule::in(CaptchaService::CaptchaType)],
            'phone_code'=>'nullable|numeric',
            'phone'=>'nullable|numeric',
            // 'cf_token'=>'nullable|string'
        ]);

        $accountType = $request->get('account_type');
        $email = $request->get('email','');
        $phoneCode = $request->get('phone_code','');
        $phone = $request->get('phone','');

        $account = '';
        if ($accountType == CommonEnums::AccountTypeEmail) {
            if (!$email) {
                throw new LogicException(__('Incorrect submitted data'));
            }
            $account = $email;
        } else {
            if (!$phoneCode || !$phone) {
                throw new LogicException(__('Incorrect submitted data'));
            }
            $account = $phoneCode.$phone;
        }

        // if ( ! $cloudflareCaptcha($request, $request->get('cf_token'))) {
        //     throw new LogicException(__('Whoops! Something went wrong'));
        // }

        $type = $request->get('type');

        $exists = $accountType == CommonEnums::AccountTypeEmail ? User::where('email', $email)->first() : User::where('phone_code', $phoneCode)->where('phone', $phone)->first();

        if ($type == CaptchaService::CaptchaTypeRegister) {
            if ($exists) {
                throw new LogicException(__('The account has been used'));
            }
        } else {
            if (!$exists) {
                throw new LogicException(__('Incorrect Account Data'));
            }
        }
        $captchaService->send($accountType,$account, $request->get('type'));
        return $this->ok(true);
    }

    /**
     * 获取上传图片地址
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws RuntimeException
     * @throws GlobalLogicException
     * @throws BindingResolutionException
     */
    public function uploadImagePath(Request $request) {
        $request->validate([
            'mimetypes'=>['required', Rule::in(['jpg','jpeg','png'])],
        ]);

        $filename = 'images/'.date('Y-m').'/'.generateFilename('.'.$request->get('mimetypes'));
        $path = (new R2Service)->getPutPresignedURLs($filename);
        return $this->ok([
            'upload_url'=>$path,
            'filepath'=>$filename,
        ]);
    }

    /**
     * 接收平仓推送
     *
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function receiveClosePosition(Request $request) {
        $request->validate([
            'token'=>'required|string',
            'items'=>'required|array',
        ]);
        $isOk = $request->get('token','') == 'LH95pupc87A1APBtfGsDMaU5wCnC9pin2B9VHshUqUztXnYXfJkQyL2rhXYp8K1A4ojs0REF0rR0q3Fqky';
        if (!$isOk) {
            throw new LogicException('bad request');
        }
        ReceiveClosePosition::dispatch($request->get('items'));
        return $this->ok(true);
    }

    /**
     * 交易引擎回调
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function engineCallback(Request $request) {
        $request->validate([
            'token'=>'required|string',
            'data'=>'required|array',
            'task'=>['required', Rule::in(CommonEnums::EngineTaskAll )],
        ]);
        Log::info("收到引擎回调",['data'=>$request->all()]);

        $isOk = $request->get('token','') == 'LH95pupc87A1APBtfGsDMaU5wCnC9pin2B9VHshUqUztXnYXfJkQyL2rhXYp8K1A4ojs0REF0rR0q3Fqky';
        if (!$isOk) {
            throw new LogicException('bad request');
        }

        switch ($request->get('task')) {
            case CommonEnums::EngineTaskLimitOrder:
                HandleEngineLimitOrderCallback::dispatch($request->get('data'));
            break;
            case CommonEnums::EngineTaskClosePosition:
                HandelEngineClosePositionCallabck::dispatch($request->get('data'));
            break;
        }

        return $this->ok(true);
    }

    /**
     * 平台支持的杠杆列表
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function leverages(Request $request) {
        $collect = [
            //key是level_id, user_level.id/users.level_id
            1=>[25],//L0
            2=>[25, 50],//L1
            3=>[25, 50, 75],//L2
            4=>[25, 50, 75, 100],//L3
        ];
        if(!$request->user()){
            return $this->ok($collect[1]);
        }

        $levelID = $request->user()->level_id;
        return $this->ok($collect[array_key_exists($levelID,$collect)?$levelID:1]);
    }

    /**
     * 关于我们
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function docAboutMe(Request $request) {
        $language = App::currentLocale();
        $content = '';
        $data = PlatformProtocol::where('proto_type', PlatformEnums::ProtocolTypeAboutMe)->where('language', $language)->first();
        if ($data) {
            $content = $data->content;
        }
        return $this->ok([
            'content'=>$content,
        ]);
    }

    /**
     * 用户协议
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function docTermsAndConditions(Request $request) {
        $language = App::currentLocale();
        $content = '';
        $data = PlatformProtocol::where('proto_type', PlatformEnums::ProtocolTypeTermsAndConditions)->where('language', $language)->first();
        if ($data) {
            $content = $data->content;
        }
        return $this->ok([
            'content'=>$content,
        ]);
    }

    /**
     * 隐私协议
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function docPrivacyPolicy(Request $request) {
        $language = App::currentLocale();
        $content = '';
        $data = PlatformProtocol::where('proto_type', PlatformEnums::ProtocolTypePrivacyPolicy)->where('language', $language)->first();
        if ($data) {
            $content = $data->content;
        }
        return $this->ok([
            'content'=>$content,
        ]);
    }

    /**
     * 热更新检查
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function updateCheck(Request $request) {
        Log::info('xxxxxx',[$request->all()]);

        $request->validate([
            'platform'=>['required', Rule::in(CommonEnums::PlatformAll)],
            'device_id'=>'required|string',
            'app_version'=>'required|string',
            'plugin_version'=>'required|string'
        ]);
        $latestVersion = PlatformVersion::where('platform',$request->get('platform'))->where('status', CommonEnums::Yes)->orderByDesc('version')->first();
        if (!$latestVersion) {
            return $this->ok([
                'need_update'=>false,
                'latest_version'=>'',
                'download_path'=>'',
                'release_content'=>'',
                'md5_sum'=>'',
            ]);
        }

        if ($latestVersion->version <= $request->get('app_version')) {
            return $this->ok([
                'need_update'=>false,
                'latest_version'=>'',
                'download_path'=>'',
                'release_content'=>'',
                'md5_sum'=>'',
            ]);
        }
        return $this->ok([
            'need_update'=>true,
            'latest_version'=>$latestVersion->version,
            'download_path'=>$latestVersion->download_path,
            'release_content'=>$latestVersion->content,
            'md5_sum'=>$latestVersion->md5_sum,
        ]);
    }
}

