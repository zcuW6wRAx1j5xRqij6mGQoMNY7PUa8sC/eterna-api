<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CoinEnums;
use App\Enums\CommonEnums;
use App\Enums\FundsEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Enums\UserIdentityEnums;
use App\Enums\WalletFuturesFlowEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\AdminUser;
use App\Models\Scopes\SalesmanScope;
use App\Models\SymbolCoin;
use App\Models\User;
use App\Models\UserIdentity;
use App\Models\UserLevel;
use App\Models\UserWalletAddress;
use App\Models\UserWalletFutures;
use App\Models\UserWalletFuturesFlow;
use App\Models\UserWalletSpot;
use App\Models\UserWalletSpotFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Internal\User\Actions\AuditIdentity;
use Internal\User\Actions\CreateUser;
use Internal\User\Actions\NewUserMsg;
use Internal\User\Actions\SubmitIdentity;
use Internal\User\Payloads\CreateUserPayload;
use Internal\Wallet\Actions\DerivativeWalletFlow;
use Internal\Wallet\Actions\SpotWalletFlow;
use Internal\Wallet\Actions\WalletFuturesFlow;
use Internal\Wallet\Actions\WalletSpotFlowList;
use InvalidArgumentException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

use function Clue\StreamFilter\append;

/** @package App\Http\Controllers\Api\Admin */
class UserController extends ApiController
{

    /**
     * 用户列表
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function list(Request $request)
    {
        $request->validate([
            'page' => 'numeric',
            'page_size' => 'numeric',
            'email' => 'nullable|string',
            'name' => 'nullable|string',
            'phone' => 'nullable|string',
            'is_verified_identity' => 'nullable|numeric',
            'uid' => 'nullable|numeric',
            'status' => 'nullable|numeric',
            'funds_lock' => 'nullable|numeric',
            'salesman' => 'nullable|numeric',
            'role_type'=>['nullable', Rule::in(CommonEnums::RoleTypeAll)]
        ]);

        $salesman = $request->get('salesman', null);
        $phone = $request->get('phone', null);
        $status = $request->get('status', null);
        $uid = $request->get('uid', null);
        $name = $request->get('name', null);
        $identity = $request->get('is_verified_identity', null);
        $email = $request->get('email', null);
        $fundsLock = $request->get('funds_lock', null);
        $roleType = $request->get('role_type',null);
        $query = User::with(['salesmanInfo']);

        $userId = $request->user()->id;
        $roleId = $request->user()->role_id;
        if($roleId == CommonEnums::salesmanRoleId){
            $query->where('salesman', $userId);
        }else{
            if($salesman){
                if($roleId == CommonEnums::salesmanLeaderRoleId && !AdminUser::where('id', $salesman)->where('parent_id',$userId)->exists()){
                    throw new LogicException(__('您无权查看该业务员绑定的用户'));
                }
                $query->where('salesman', $salesman);
            }else{
                if($roleId == CommonEnums::salesmanLeaderRoleId){
                    // 组长搜索自己下属业务员绑定的用户
                    $query->whereIn('salesman', function ($query) use ($userId) {
                        $query->select('id')->from('admin_user')->where('parent_id', $userId);
                    });
                }
            }
        }

        if ($fundsLock) {
            $query->where('funds_lock', $fundsLock);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($uid !== null) {
            $query->where('id', $uid);
        }
        if ($phone) {
            $query->where('phone', $phone);
        }
        if ($roleType !== null) {
            $query->where('role_type', $roleType);
        }
        if ($name) {
            $query->where('name', 'like', '%' . $name . '%');
        }
        if ($identity !== null) {
            $query->where('is_verified_identity', $identity);
        }
        if ($email) {
            $query->where('email', 'like', '%' . $email . '%');
        }

        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size'), ['*'], null, $request->get('page'));
        return $this->ok(listResp($data));
    }


    /**
     * 绑定业务员
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function bindSalesman(Request $request)
    {
        $request->validate([
            'uid'       => 'required|integer',
            'salesman'  => 'required|integer',
        ]);
        $salesmanID = $request->get('salesman');
        $salesman   = AdminUser::find($salesmanID);
        if(!$salesman){
            throw new LogicException('Salesman not exists.');
        }

        $user = User::find($request->get('uid'));
        if ($user->salesman == $salesmanID){
            return $this->ok();
        }

        $user->salesman     = $salesmanID;
        $user->parent_id    = $salesmanID;
        $user->invite_code  = $salesman->invite_code;
        $user->save();

        return $this->ok(true);
    }

    /**
     * 用户等级列表
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function userLevels(Request $request) {
        return $this->ok(UserLevel::all());
    }

    /**
     * 创建新用户
     * @param Request $request
     * @param CreateUser $createUser
     * @return JsonResponse
     * @throws BadRequestException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function create(Request $request, CreateUser $createUser)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'salesman' => 'nullable|integer',
            'is_verified_identity' => ['required', Rule::in(CommonEnums::Yes, CommonEnums::No)]
        ]);

        $salesman   = $request->get('salesman',0);
        if(!$salesman){
            $salesman == $request->user()->id;
        }
        if($salesman && $salesman != $request->user()->id && !AdminUser::find($salesman)){
            throw new LogicException(__('业务员ID不存在'));
        }
        $payload                = new CreateUserPayload;
        $payload->accountType   = CommonEnums::AccountTypeEmail;
        $payload->isAdmin       = true;
        $payload->salesman      = $salesman;
        $payload->parseFromRequest($request);
        return $this->ok($createUser($payload));
    }


    /**
     * 个人信息配置
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function setting(Request $request)
    {
        $request->validate([
            'uid' => 'required|numeric',
            'name' => 'string',
            'email' => 'nullable|string|email',
            'password' => 'string',
            'trade_password' => ['numeric', Password::min(6)->max(6)],
            'status' => ['nullable', 'numeric', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'funds_lock' => ['nullable', 'numeric', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'level'=>'nullable|numeric',
            'role_type'=>['nullable','numeric',Rule::in(CommonEnums::RoleTypeAll)],
        ]);

        $level = $request->get('level',null);
        $name = $request->get('name', '');
        $email = $request->get('email', '');
        $password = $request->get('password', '');
        $tradePwd = $request->get('trade_password', '');
        $status = $request->get('status', null);
        $fundsLock = $request->get('funds_lock', null);
        $roleType = $request->get('role_type',null);
        $user = User::findOrFail($request->get('uid'));
        if ($name) {
            $user->name = $name;
        }
        if ($level !== null) {
            $user->level_id = $level;
        }
        if ($email) {
            $exists = User::where('id', '!=', $request->get('uid'))->where('email', $email)->first();
            if ($exists) {
                throw new LogicException('邮箱已存在');
            }
        }
        if ($password) {
            $user->password = Hash::make($password);
        }
        if ($roleType) {
            $user->role_type = $roleType;
        }
        if ($tradePwd) {
            $user->trade_password = Hash::make($tradePwd);
        }
        if ($fundsLock !== null) {
            $user->funds_lock = $fundsLock;
        }
        if ($status !== null) {
            $user->status = $status;
        }
        $user->save();

        if ($status !== null && $status == CommonEnums::No) {
            $user->logoutAllDevice();
        }

        return $this->ok(true);
    }

    /**
     * 现货钱包流水
     * @param Request $request
     * @param SpotWalletFlow $spotWalletFlow
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function spotWalletFlow(Request $request, WalletSpotFlowList $spotWalletFlow)
    {
        $request->validate([
            'page' => 'numeric',
            'page_size' => 'numeric',
            'flow_type' => ['nullable', Rule::in(SpotWalletFlowEnums::Maps)],
            'coin_id' => 'nullable|numeric',
            'uid' => 'required|numeric',
        ]);

        $user = User::find($request->get('uid'));
        return $this->ok($spotWalletFlow($request, $user));
    }

    /**
     * 合约钱包流水
     * @param Request $request
     * @param DerivativeWalletFlow $derivativeWalletFlow
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function derivativeWalletFlow(Request $request, WalletFuturesFlow $walletFuturesFlow)
    {
        $request->validate([
            'page' => 'numeric',
            'page_size' => 'numeric',
            'flow_type' => ['nullable', Rule::in(WalletFuturesFlowEnums::Maps)],
            'uid' => 'required|numeric',
        ]);
        $user = User::find($request->get('uid'));
        return $this->ok($walletFuturesFlow($request, $user));
    }

    /**
     * 修改用户现货钱包
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function modifySpotWallet(Request $request)
    {
        $request->validate([
            'uid' => 'required|numeric',
            'coin_id' => 'required|numeric',
            'money' => 'required|numeric'
        ]);

        if(!SymbolCoin::find($request->get('coin_id'))){
            throw new LogicException(__('不存在的币种'));
        }

        DB::transaction(function () use ($request) {
            $wallet = UserWalletSpot::where('uid', $request->get('uid'))->where('coin_id', $request->get('coin_id'))->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = new UserWalletSpot();
                $wallet->uid = $request->get('uid');
                $wallet->coin_id = $request->get('coin_id');
                $wallet->save();

                $wallet = UserWalletSpot::where('uid', $request->get('uid'))->where('coin_id', $request->get('coin_id'))->lockForUpdate()->first();
            }

            $money = $request->get('money', 0);
            $before = $wallet->amount;
            $wallet->amount = bcadd($wallet->amount, $money, FundsEnums::DecimalPlaces);
            $wallet->save();

            $quoteFlow = new UserWalletSpotFlow();
            $quoteFlow->uid = $request->get('uid');
            $quoteFlow->coin_id = $request->get('coin_id');
            $quoteFlow->flow_type = $money >= 0 ? SpotWalletFlowEnums::FlowTypeSystemDeposit : SpotWalletFlowEnums::FlowTypeSystemWithdraw;
            $quoteFlow->before_amount = $before;
            $quoteFlow->amount = $money;
            $quoteFlow->after_amount = $wallet->amount;
            $quoteFlow->relation_id = 0;
            $quoteFlow->save();

            return true;
        });
        return $this->ok(true);
    }

    /**
     * 修改用户合约钱包
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function modifyDerivativeWallet(Request $request)
    {
        $request->validate([
            'uid' => 'required|numeric',
            'money' => 'required|numeric'
        ]);

        DB::transaction(function () use ($request) {
            $wallet = UserWalletFutures::where('uid', $request->get('uid'))->lockForUpdate()->first();
            if (!$wallet) {
                throw new LogicException(__('没有找到用户钱包数据'));
            }

            $money = $request->get('money', 0);
            $before = $wallet->balance;
            $wallet->balance = bcadd($wallet->balance, $money, FundsEnums::DecimalPlaces);
            $wallet->save();

            $flow = new UserWalletFuturesFlow();
            $flow->uid = $request->get('uid');
            $flow->flow_type = WalletFuturesFlowEnums::FlowSystem;
            $flow->before_amount = $before;
            $flow->amount = $money;
            $flow->after_amount = $wallet->balance;
            $flow->relation_id = 0;
            $flow->save();

            return true;
        });
        return $this->ok(true);
    }

    /**
     * 现货钱包数据
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function userSpotWallet(Request $request)
    {
        $request->validate([
            'uid' => 'required|numeric',
        ]);

        $wallet = UserWalletSpot::with(['coin'])->where('uid', $request->get('uid'))->get();
        if (!$wallet) {
            throw new LogicException('没有找到钱包数据');
        }
        return $this->ok([
            'wallet' => $wallet,
            'wallet_usdt' => UserWalletSpot::with(['coin'])->where('uid', $request->get('uid'))->where('coin_id', CoinEnums::DefaultUSDTCoinID)->first(),
            'wallet_address' => UserWalletAddress::with(['platform'])->where('uid', $request->get('uid'))->get(),
        ]);
    }

    /**
     * 用户合约钱包
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function userDerivativeWallet(Request $request)
    {
        $request->validate([
            'uid' => 'required|numeric',
        ]);
        $data = UserWalletFutures::where('uid', $request->get('uid'))->first();
        return $this->ok($data);
    }

    /**
     * 修改备注
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function editRemark(Request $request)
    {
        $request->validate([
            'uid' => 'required|numeric',
            'content' => 'required|string',
        ]);
        $user = User::find($request->get('uid'));
        if (!$user) {
            throw new LogicException('用户数据不正确');
        }
        $user->remark = $request->get('content');
        $user->save();
        return $this->ok(true);
    }

    /**
     * 去除用户实名记录
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function removeUserIdentity(Request $request)
    {
        $request->validate([
            'uid' => 'required|numeric'
        ]);

        DB::transaction(function () use ($request) {
            $user = User::find($request->get('uid'));
            if (!$user) {
                throw new LogicException('数据不正确');
            }
            if (! $user->is_verified_identity) {
                throw new LogicException('该用户目前没有通过实名, 无法操作');
            }

            $user->is_verified_identity = CommonEnums::No;
            $user->save();

            $identity = UserIdentity::where('uid', $user->id)->where('process_status', CommonEnums::Yes)->first();
            if ($identity) {
                $identity->delete();
            }
            return true;
        });
        return $this->ok(true);
    }

    /**
     * 用户实名列表
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function userIdentityList(Request $request)
    {
        $request->validate([
            'page' => 'numeric',
            'page_size' => 'numeric',
            'status' => ['nullable', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'uid'=>'nullable|numeric',
            'email' => 'nullable|string',
            'phone' => 'nullable|string',
        ]);

        $uid = $request->get('uid', null);
        $phone = $request->get('phone', null);
        $email = $request->get('email', null);
        $status = $request->get('status', null);
        $query = UserIdentity::with(['user', 'country']);
        if ($status !== null) {
            $query->where('status', $status);
        }
        $queryUids = [];

        if ($email) {
            $_query = User::select(['id'])->where('email', 'like', '%' . $email . '%')->get();
            if ($_query->isEmpty()) {
                return $this->ok([]);
            }
            $queryUids = $_query->pluck('id')->toArray();
        }
        if ($phone) {
            $_query = User::select(['id'])->where('phone', 'like', '%' . $phone . '%')->get();
            if ($_query->isEmpty()) {
                return $this->ok([]);
            }
            $queryUids = array_unique(array_merge($queryUids, $_query->pluck('id')->toArray()));
        }

        if ($queryUids) {
            $query->whereIn('uid', $queryUids);
        }
        if ($uid !== null) {
            $query->where('uid', $uid);
        }

        $query->withGlobalScope('salesman_scope', new SalesmanScope());
        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size'), ['*'], null, $request->get('page'));
        return $this->ok(listResp($data));
    }

    /**
     * 审核实名认证
     * @param Request $request
     * @param AuditIdentity $auditIdentity
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function auditIdentity(Request $request, AuditIdentity $auditIdentity)
    {
        $request->validate([
            'id' => 'required|numeric',
            'audit' => ['required', Rule::in([UserIdentityEnums::ProcessRejected, UserIdentityEnums::ProcessPassed])],
            'reason' => 'nullable|string',
        ]);
        $auditIdentity($request);
        return $this->ok(true);
    }

    /**
     * 发送用户站内行
     * @param Request $request
     * @param NewUserMsg $newUserMsg
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function sendUserMsg(Request $request, NewUserMsg $newUserMsg)
    {
        $request->validate([
            'uid' => 'required|numeric',
            'subject' => 'required|string',
            'content' => 'required|string',
        ]);

        $newUserMsg($request->get('uid'), $request->get('subject'), $request->get('content'));
        return $this->ok(true);
    }

    /**
     * 手动提交实名
     * @param Request $request
     * @param SubmitIdentity $submitIdentity
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function submitIdentity(Request $request, SubmitIdentity $submitIdentity)
    {
        $request->validate([
            'uid'=> 'required|numeric',
            'document_type' => ['required', Rule::in(UserIdentityEnums::DocumentTypeMaps)],
            'country_id' => 'required|numeric',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'document_number' => 'required|string',
            'face' => 'required|string',
            'document_frontend' => 'required|string',
            'document_backend' => 'string',
        ]);
        $submitIdentity($request);
        return $this->ok(true);
    }
}
