<?php

namespace Internal\User\Actions;

use App\Enums\CommonEnums;
use App\Enums\UserIdentityEnums;
use App\Http\Resources\UserResource;
use App\Models\UserIdentity;
use Illuminate\Http\Request;

class Profile {

    public function __invoke(Request $request)
    {
        return new UserResource($request->user());
    }

    /**
     * 实名认证状态回显
     * @param Request $request
     * @return array
     */
    public function indentityStatus(Request $request) {
        $user = $request->user();
        if ($user->is_verified_identity == CommonEnums::Yes) {
            return [
                'status'=>UserIdentityEnums::ProcessPassed,
                'data'=>[],
            ];
        }
        $model = UserIdentity::with('country')->where('uid', $request->user()->id)->orderByDesc('created_at')->first();
        if (!$model) {
            return [
                'status'=>-1,
                'data'=>[],
            ];
        }
        return [
            'status'=>$model->process_status,
            'data'=>$model,
        ];
    }
}

