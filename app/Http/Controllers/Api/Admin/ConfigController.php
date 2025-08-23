<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CommonEnums;
use App\Enums\ConfigEnums;
use App\Enums\PlatformEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\PlatformAnnouncement;
use App\Models\PlatformBanner;
use App\Models\PlatformConfig;
use App\Models\PlatformNotice;
use App\Models\PlatformProtocol;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Internal\Common\Services\ConfigService;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class ConfigController extends ApiController {

    /**
     * Banner 列表
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function banners(Request $request) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'status'=>['nullable',Rule::in([CommonEnums::Yes,CommonEnums::No])]
        ]);

        $status = $request->get('status', null);
        $query = PlatformBanner::query();
        if ($status !== null) {
            $query->where('status', $status);
        }

        $data = $query->orderBy('sort')->paginate($request->get('page_size'),['*'],null, $request->get('page'));
        return $this->ok(listResp($data));
    }

    /**
     * 修改或新增banner
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function modifyBanners(Request $request) {
        $request->validate([
            'id'=>'numeric',
            'img_path'=>'required|string',
            'link_url'=>'string',
            'sort'=>'required|numeric',
            'status'=>['nullable', Rule::in([CommonEnums::Yes,CommonEnums::No])],
        ]);

        $id = $request->get('id',0);
        $linkUrl = $request->get('link_url','');
        $status = $request->get('status',null);
        $model = $id ? PlatformBanner::findOrFail($id) : new PlatformBanner();
        $model->platform = 'app';
        $model->img_path = $request->get('img_path');
        $model->sort = $request->get('sort');
        if ($status !== null) {
            $model->status = $status;
        }
        if ($linkUrl) {
            $model->link_url = $linkUrl;
        }
        $model->save();
        return $this->ok(true);
    }

    public function deleteBanner(Request $request) {
        $request->validate([
            'id'=>'required|numeric',
        ]);

        $model = PlatformBanner::find($request->get('id'));
        if (!$model) {
            throw new LogicException('数据不正确');
        }
        $model->delete();
        return $this->ok(true);
    }

    /**
     * 公告列表
     * @param Request $request
     * @reurn JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function announcements(Request $request) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'status'=>['nullable',Rule::in([CommonEnums::Yes,CommonEnums::No])]
        ]);

        $status = $request->get('status', null);
        $query = PlatformAnnouncement::query();
        if ($status !== null) {
            $query->where('status', $status);
        }
        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size'),['*'],null, $request->get('page'));
        return $this->ok(listResp($data));
    }

    /**
     * 修改 / 新增公告
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function modifyAnnouncements(Request $request) {
        $request->validate([
            'id'=>'numeric',
            'title'=>'required|string',
            'content'=>'required|string',
            'status'=>['nullable', Rule::in([CommonEnums::Yes,CommonEnums::No])],
        ]);

        $id = $request->get('id',0);
        $status = $request->get('status',null);
        $model = $id ? PlatformAnnouncement::findOrFail($id) : new PlatformAnnouncement();
        $model->title = $request->get('title');
        $model->content = $request->get('content');
        if ($status !== null) {
            $model->status = $status;
        }
        $model->save();
        return $this->ok(true);
    }

    /**
     * 系统通知
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function notices(Request $request) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'status'=>['nullable',Rule::in([CommonEnums::Yes,CommonEnums::No])]
        ]);

        $status = $request->get('status', null);
        $query = PlatformNotice::query();
        if ($status !== null) {
            $query->where('status', $status);
        }

        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size'),['*'],null, $request->get('page'));
        return $this->ok(listResp($data));
    }

    /**
     * 处理系统消息
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function modifyNotices(Request $request) {
        $request->validate([
            'id'=>'numeric',
            'status'=>['nullable', Rule::in([CommonEnums::Yes,CommonEnums::No])],
            'subject'=>'required|string',
            'content'=>'required|string',
        ]);

        $id = $request->get('id',0);
        $status = $request->get('status',null);
        $model = $id ? PlatformNotice::findOrFail($id) : new PlatformNotice();
        $model->subject = $request->get('subject');
        $model->content = $request->get('content');
        if ($status !== null) {
            $model->status = $status;
        }
        $model->save();
        return $this->ok(true);
    }

    /**
     * 删除通知
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function deleteNotice(Request $request) {
        $request->validate([
            'id'=>'required|numeric',
        ]);
        $model = PlatformNotice::find($request->get('id'));
        if (!$model) {
            throw new LogicException('数据不正确');
        }
        $model->delete();
        return $this->ok(true);
    }

    /**
     * 设置隐私协议
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function setProtocols(Request $request) {
        $request->validate([
            'proto_type'=>['required', Rule::in(PlatformEnums::ProtocolMaps)],
            'content'=>'required|string',
            'language'=>['required', Rule::in(CommonEnums::LanguageAll)],
        ]);

        $type = $request->get('proto_type');
        $content = $request->get('content');
        $language = $request->get('language');

        $model = PlatformProtocol::where('proto_type', $type)->where('language',$language)->first();
        if (!$model) {
            $model = new PlatformProtocol();
            $model->proto_type = $type;
            $model->content = $content;
            $model->language = $language;
            $model->save();
            return $this->ok(true);
        }
        $model->proto_type = $type;
        $model->content = $content;
        $model->language = $language;
        $model->save();
        return $this->ok(true);
    }

    /**
     * 协议列表
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function protocols(Request $request) {
        $data = PlatformProtocol::get();
        if ($data->isEmpty()) {
            return $this->ok([]);
        }

        $all = [];
        $data->each(function($item) use(&$all){
            $cur = $all[$item->proto_type] ?? [];
            if (!$cur) {
                $all[$item->proto_type] = [];
            }
            $all[$item->proto_type][$item->language] = $item->content;
            return true;
        });
        return $this->ok($all);
    }

    /**
     * 获取配置信息
     * @throws BindingResolutionException
     */
    public function configs(Request $request): JsonResponse
    {
        $request->validate([
            'field'=>['nullable', Rule::in(ConfigEnums::CategoryPlatformCfgs)],
        ]);

        $allConfigs = ConfigEnums::CategoryPlatformCfgs;
        $field = $request->get('field','');
        if ($field) {
            if (!in_array($field, $allConfigs)) {
                return $this->fail("field {$field} not found");
            }

            $configs = PlatformConfig::query()
                ->where('category', 'platform')
                ->where('key', $field)
                ->value('value');
            return $this->ok($configs);
        }

        $default    = [];
        foreach ($allConfigs as $config) {
            $default[$config] = 0;
        }
        $configs = PlatformConfig::query()
            ->where('category', 'platform')
            ->whereIn('key', array_keys($default))
            ->pluck('value', 'key')
            ->toArray();
        $configs = array_merge($default, $configs);

        return $this->ok($configs);
    }

    /**
     * 修改配置
     * @throws BindingResolutionException
     */
    public function modifyConfig(Request $request): JsonResponse
    {
        $request->validate([
            'field' => ['required', Rule::in(ConfigEnums::CategoryPlatformCfgs)],
            'value' => 'required|numeric|min:0|max:100',
        ]);

        PlatformConfig::where('category', 'platform')
            ->where('key', $request->get('field'))
            ->update(['value' => $request->get('value')]);

        ConfigService::getIns()->refresh();

        return $this->ok();
    }
}

