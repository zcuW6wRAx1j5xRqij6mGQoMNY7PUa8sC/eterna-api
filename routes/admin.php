<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\MenuController;
use App\Http\Controllers\Api\Admin\RoleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/



Route::post('login',[AdminController::class, 'login']);
Route::get('menu/selector',          [MenuController::class, 'selector']);//菜单选择框
Route::get('role/selector',          [RoleController::class, 'selector']);//角色选择框

Route::middleware(['auth:sanctum', 'auth.rbac'])->group(function() {
//Route::middleware([])->group(function() {
    Route::post('logout', [AdminController::class, 'logout']);

    Route::prefix('account')->group(function(){
        Route::get('show',                      [AdminController::class, 'show']);//列表
        Route::post('store',                    [AdminController::class, 'store']);//新增
        Route::post('destroy/{id}',             [AdminController::class, 'destroy']);//删除
        Route::post('update/{id}',              [AdminController::class, 'update']);//修改
        Route::get('detail/{id}',               [AdminController::class, 'detail']);//详细信息
        Route::post('resetPwd/{id}',            [AdminController::class, 'resetPwd']);//重置密码
        Route::post('freeze/{id}',              [AdminController::class, 'freeze']);//冻结账号
        Route::post('assignRole/{id}/{rid}',    [AdminController::class, 'assignRole']);//分配角色
    });

    Route::prefix('menu')->group(function(){
        Route::get('index',             [MenuController::class, 'index']);//个人菜单
        Route::get('show',              [MenuController::class, 'show']);//列表
        Route::post('store',            [MenuController::class, 'store']);//新增
        Route::post('destroy/{id}',     [MenuController::class, 'destroy']);//删除
        Route::post('update/{id}',      [MenuController::class, 'update']);//修改
        Route::get('detail/{id}',       [MenuController::class, 'detail']);//详细信息
    });

    Route::prefix('role')->group(function(){
        Route::get('show',              [RoleController::class, 'show']);//列表
        Route::post('store',            [RoleController::class, 'store']);//新增
        Route::post('destroy/{id}',     [RoleController::class, 'destroy']);//删除
        Route::post('update/{id}',      [RoleController::class, 'update']);//修改
        Route::get('detail/{id}',       [RoleController::class, 'detail']);//详细信息
        Route::post('assignMenus/{id}', [RoleController::class, 'assignMenus']);//分配菜单
    });

});
