<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Route;

class Rbac{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        if($user->role_id == 1){
            return $next($request);
        }

        if(!$user || !method_exists($user, 'hasPermission')){
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        // 检查用户是否具有该权限
        if (!$user->hasPermission($user->role_id, '/' . Route::current()->uri())) {
            // 用户没有权限，返回禁止访问响应
            return response()->json(['message' => 'Permission denied.'], 403);
        }

        return $next($request);
    }
}
