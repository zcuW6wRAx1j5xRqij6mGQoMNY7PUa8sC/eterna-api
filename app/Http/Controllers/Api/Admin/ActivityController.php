<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CommonEnums;
use App\Enums\PlatformEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\Mentor;
use App\Models\PlatformBanner;
use App\Models\PlatformNotice;
use App\Models\PlatformProtocol;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/** @package App\Http\Controllers\Api\Admin */
class ActivityController extends ApiController {

    /**
     * 导师列表
     * @param Request $request 
     * @return JsonResponse 
     * @throws BindingResolutionException 
     */
    public function mentos(Request $request) {
        $data = Mentor::query()->get();
        return $this->ok($data);
    }
    
    /**
     * 新增导师
     * @param Request $request 
     * @return JsonResponse 
     * @throws BadRequestException 
     * @throws BindingResolutionException 
     * @throws NotFoundExceptionInterface 
     * @throws ContainerExceptionInterface 
     * @throws LogicException 
     * @throws InvalidArgumentException 
     * @throws InvalidCastException 
     */
    public function newMento(Request $request) {
        $request->validate([
            'name'=>'required|string',
            'avatar'=>'required|string',
            'description'=>'required|string',
        ]);

        $name = $request->get('name');
        $avatar = $request->get('avatar');
        $description = $request->get('description');

        if (Mentor::where('name', $name)->first()) {
            throw new LogicException(__('导师名称已存在'));
        }

        $mentor = new Mentor();
        $mentor->name = $name;
        $mentor->avatar = $avatar;
        $mentor->votes = 0;
        $mentor->process = 0;
        $mentor->description = $description;
        $mentor->save();
        return $this->ok(true);
    }

    /**
     * 修改导师
     * @param Request $request 
     * @return JsonResponse 
     * @throws BadRequestException 
     * @throws BindingResolutionException 
     * @throws NotFoundExceptionInterface 
     * @throws ContainerExceptionInterface 
     * @throws LogicException 
     */
    public function editMento(Request $request) {
        $request->validate([
            'id'=>'required|numeric',
            'name'=>'nullable|string',
            'avatar'=>'nullable|string',
            'description'=>'nullable|string',
            'votes'=>'nullable|numeric',
            'process'=>'nullable|numeric',
            'status'=>['nullable', Rule::in([CommonEnums::Yes,CommonEnums::No])],
        ]);

        $mentor = Mentor::find($request->get('id'));
        if (!$mentor) {
            throw new LogicException(__('导师不存在'));
        }

        $name = $request->get('name');
        $avatar = $request->get('avatar');
        $description = $request->get('description');
        $votes = $request->get('votes');
        $process = $request->get('process');
        $status = $request->get('status');

        if ($name !== null) {
            if (Mentor::where('name', $name)->where('id', '!=', $request->get('id'))->first()) {
                throw new LogicException(__('导师名称已存在'));
            }
            $mentor->name = $name;
        }
        if ($avatar !== null) {
            $mentor->avatar = $avatar;
        }
        if ($description !== null) {
            $mentor->description = $description;
        }
        if ($votes !== null) {
            $mentor->votes = $votes;
        }
        if ($process !== null) {
            $mentor->process = $process;
        }
        if ($status !== null) {
            $mentor->status = $status;
        }
        $mentor->save();
        return $this->ok(true);
    }
}

