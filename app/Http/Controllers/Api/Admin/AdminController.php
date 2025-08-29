<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CommonEnums;
use App\Exceptions\LogicException;
use App\Models\AdminRole;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiController;
use App\Models\AdminUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use InvalidArgumentException;

class AdminController extends ApiController
{

    public function login(Request $request)
    {
        $request->validate([
            'username'  => 'required|string',
            'password'  => 'required|string',
        ]);

        $username   = $request->get('username');
        $user       = AdminUser::where('username',$username)->first();
        if (!$user) {
            throw new LogicException(__('Incorrect username or password'));
        }

        if ($user->status != CommonEnums::Yes) {
            throw new LogicException(__('Your account is currently unavailable, if you have any questions, please contact customer service'));
        }

        if (!Hash::check($request->get('password'), $user->password)) {
            throw new LogicException(__('Incorrect username or password'));
        }
        $token = $user->generateToken();
        $user->last_login_time = Carbon::now();
        $user->save();

        return $this->ok([
            'token'         => $token,
            'expires_at'    => AdminUser::DefaultTokenTTL,
        ]);
    }

    /**
     * 登出
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function logout(Request $request) {
        $request->user()->logout();
        return $this->ok(true);
    }


    /**
     * 新增管理员
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws LogicException
     * @throws InvalidArgumentException
     * @throws InvalidCastException
     * @throws BindingResolutionException
     */
    public function store(Request $request)
    {
        $request->validate([
            'nickname'=>'nullable|string',
            'username'=>'required|string',
            'role_id'=>'nullable|numeric',
            'invite_code'=>'nullable|string',
            'password'=>['required', Password::min(8)->max(16)->mixedCase()->numbers()],
        ]);

        $exists = AdminUser::where('username', $request->get('username'))->first();
        if ($exists) {
            throw new LogicException('用户名已存在');
        }

        $user = new AdminUser();
        $user->nickname = $request->get('nickname');
        $user->username = $request->get('username');
        $user->role_id = (int)$request->get('role_id');

        if($request->user()->role_id == CommonEnums::salesmanLeaderRoleId){
            $user->role_id = CommonEnums::salesmanRoleId;
        }

        $user->invite_code = (string)$request->get('invite_code');
        if(!$user->invite_code){
            $user->invite_code = AdminUser::generateInviteCode();
        }

        if(!$user->nickname){
            $user->nickname = $user->username;
        }
        $user->password = Hash::make($request->get('password'));
        $user->save();

        return $this->ok(true);
    }

    /**
     * 列表
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function list(Request $request) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'username'=>'nullable|string',
            'role_id'=>'nullable|integer',
            'status'=>['nullable',Rule::in([CommonEnums::Yes,CommonEnums::No])]
        ]);
        $query = AdminUser::query()->with(['parent']);
        $status = $request->get('status',null);
        $username = $request->get('username',null);
        $roleId = $request->get('role_id',0);
        if($request->user()->role_id == CommonEnums::salesmanLeaderRoleId){
            $roleId = CommonEnums::salesmanRoleId;
        }
        if ($status != null) {
            $query->where('status', $status);
        }
        if ($username) {
            $query->where('username','like','%'.$username.'%');
        }
        if ($roleId) {
            $query->where('role_id',$roleId);
        }
        $data = $query->orderByDesc('id')->paginate($request->get('page_size',15),['*'],null, $request->get('page',1));
        return $this->ok(listResp($data));
    }

    /**
     * 角色选择下拉框
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function roleOptions(Request $request) {
        if($request->user()->role_id == CommonEnums::salesmanLeaderRoleId){
            $ret = AdminRole::where('id', CommonEnums::salesmanRoleId)->pluck('name', 'id');
            return $this->ok($ret);
        }
        return $this->ok(AdminRole::pluck('show_name','id'));
    }

    /**
     * 业务员组长选择下拉框
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function salesmanLeaderOptions(Request $request)
    {
        if($request->user()->role_id == CommonEnums::salesmanLeaderRoleId){
            $ret = AdminUser::select('id', 'nickname', 'username')->find($request->user()->id);
            return $this->ok($ret);
        }
        $result = AdminRole::select('au.id', 'au.nickname', 'au.username')
            ->join('admin_user as au', 'au.role_id', '=', 'admin_role.id')
            ->where('role_id', CommonEnums::salesmanLeaderRoleId)
            ->get();

        return $this->ok($result);
    }

    /**
     * 业务员选择下拉框
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function salesmanOptions(Request $request)
    {
        $uid = $request->user()->id;
        if($request->user()->role_id == CommonEnums::salesmanRoleId){
            $ret = AdminUser::select('id', 'nickname', 'username')->find($uid);
            return $this->ok($ret);
        }
        $query = AdminRole::select('au.id', 'au.nickname', 'au.username')
            ->join('admin_user as au', 'au.role_id', '=', 'admin_role.id')
            ->where('role_id', CommonEnums::salesmanRoleId);

        if($request->user()->role_id == CommonEnums::salesmanLeaderRoleId) {
            $query = $query->where('parent_id', $uid);
        }

        $result = $query->get();

        return $this->ok($result);
    }

    /**
     * 业务员分配组长
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function bindParent(Request $request){
        $request->validate([
            'leader_id'=>'required|integer',
            'salesman_id'=>'required|integer',
        ]);
        $leaderId = $request->get('leader_id');
        $salesmanId = $request->get('salesman_id');

        if(AdminUser::where('id', $leaderId)->value('role_id') != CommonEnums::salesmanLeaderRoleId){
            throw new LogicException("{$leaderId} 不是业务员组长");
        }

        $salesman = AdminUser::find($salesmanId);
        if($salesman->role_id != CommonEnums::salesmanRoleId){
            throw new LogicException("{$leaderId} 不是业务员");
        }

        if($salesman->parent_id!=0){
            if($salesman->parent_id == $leaderId){
                return $this->ok();
            }
            throw new LogicException("{$leaderId} 业务员已存在绑定关系");
        }

        $salesman->update(['parent_id' => $leaderId]);

        return $this->ok();
    }

    /**
     * 取消业务员绑定关系
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function cancelBindParent(Request $request){
        $request->validate([
            'leader_id'=>'required|integer',
            'salesman_id'=>'required|integer',
        ]);
        $leaderId = $request->get('leader_id');
        $salesmanId = $request->get('salesman_id');

        AdminUser::where('id', $salesmanId)->where('parent_id', $leaderId)->update(['parent_id' => 0]);

        return $this->ok();
    }

    /**
     * 删除管理员账户
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'id'=>'required|numeric',
        ]);
        $user = AdminUser::find($request->get('id'));
        if (!$user) {
            throw new LogicException('用户ID不正确');
        }
        $user->delete();
        return $this->ok(true);
    }

    /**
     * 修改用户信息
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function edit(Request $request)
    {
        $request->validate([
            'id'=>'required|numeric',
            'password'=>['nullable',Password::min(8)->max(16)->mixedCase()->numbers()],
            'status'=>['nullable',Rule::in([CommonEnums::Yes,CommonEnums::No])]
        ]);
        $user = AdminUser::find($request->get('id'));
        if(!$user){
            throw new LogicException('User not exists.');
        }
        $password = $request->get('password',null);
        $status = $request->get('status',null);

        if ($password) {
            $user->password = Hash::make($password);
        }
        if ($status !== null) {
            $user->status = $status;
        }
        $user->save();
        return $this->ok(true);
    }

    /**
     * 管理员个人信息
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function detail(Request $request) {
        return $this->ok($request->user());
    }

    /**
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
    public function create(Request $request) {
        $request->validate([
            'nickname'=>'required|string',
            'username'=>'required|string',
            'password'=>'required|string',
            'invite_code'=>'nullable|string',
        ]);

        $exist = AdminUser::where('username', $request->get('username'))->first();
        if ($exist) {
            throw new LogicException('用户名已存在');
        }

        $user = new AdminUser();
        $user->nickname = $request->get('nickname');
        $user->username = $request->get('username');
        $user->invite_code = $request->get('invite_code');
        $user->password = Hash::make($request->get('password'));
        $user->operator = $request->user()->id;
        $user->save();
        return $this->ok(true);
    }

    /**
     * 修改信息
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     */
    public function setting(Request $request) {
        $request->validate([
            'uid'=>'required|numeric',
            'role_id'=>'required|numeric',
            'status'=>['nullable', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'password'=>['nullable', Password::min(8)],
            'nickname'=>['nullable','string'],
        ]);
        $user = AdminUser::find($request->get('uid'));
        if (!$user) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }
        $status = $request->get('status',null);
        $password = $request->get('password','');
        $nickname = $request->get('nickname','');
        $inviteCode = $request->get('invite_code','');
        $roleId   = $request->get('role_id',0);
        if($user->role_id != $roleId){
            if($user->role_id == CommonEnums::salesmanRoleId && $user->parent_id > 0){
                throw new LogicException(__('请先解绑已存在的业务员绑定关系'));
            }
            if($user->role_id == CommonEnums::salesmanLeaderRoleId && AdminUser::where('parent_id', $user->id)->count() > 0){
                throw new LogicException(__('请先解绑已存在的业务员绑定关系'));
            }
        }
        if ( !is_null($status)) {
            $user->status = $status;
        }
        if ($password) {
            $user->password = Hash::make($password);
        }
        if ($nickname) {
            $user->nickname = $nickname;
        }
        if ($inviteCode) {
            $user->invite_code = $inviteCode;
        }
        $user->role_id = $roleId;
        $user->save();
        return $this->ok(true);
    }

}
