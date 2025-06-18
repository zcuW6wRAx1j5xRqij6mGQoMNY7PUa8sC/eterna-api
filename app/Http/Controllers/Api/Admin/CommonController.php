<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CommonEnums;
use App\Enums\PlatformEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\AdminUser;
use App\Models\PlatformProtocol;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\Concerns\Has;
use Illuminate\Validation\Rule;
use Internal\Common\Services\R2Service;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use InvalidArgumentException;

class CommonController extends ApiController {

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
        $filename = 'images/source/'.date('Y-m').'/'.generateFilename('.'.$request->get('mimetypes'));
        $path = (new R2Service)->getPutPresignedURLs($filename);
        return $this->ok([
            'upload_url'=>$path,
            'filepath'=>$filename,
        ]);
    }


}

