<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CommonEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\Concerns\Has;
use Internal\Tools\Services\CentrifugalService;

class AuthController extends ApiController {

    public function login(Request $request) {
        $request->validate([
            'username'=>'required|string',
            'password'=>'required|string',
        ]);
        $user =AdminUser::where('username', $request->get('username'))->first();
        if (!$user) {
            throw new LogicException('用户名或密码不正确');
        }
        if ($user->status != CommonEnums::Yes) {
            throw new LogicException(__('Your account is currently unavailable, if you have any questions, please contact customer service'));
        }
        if ( ! Hash::check($request->get('password'), $user->password)) {
            throw new LogicException(__('Incorrect username or password'));
        }
        return $this->ok([
            'token'=>$user->generateToken(),
            'expires_at'=>AdminUser::DefaultTokenTTL,
            'ws_token'=>CentrifugalService::getInstance()->generateAdminJwt($user->id),
        ]);
    }
}

