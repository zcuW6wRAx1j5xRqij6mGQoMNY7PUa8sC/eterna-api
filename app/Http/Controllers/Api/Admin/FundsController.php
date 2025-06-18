<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CommonEnums;
use App\Enums\FundsEnums;
use App\Enums\TransferEnums;
use App\Events\AuditWithdraw;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\AdminUser;
use App\Models\Scopes\SalesmanScope;
use App\Models\User;
use App\Models\UserDeposit;
use App\Models\UserWithdraw;
use Carbon\Carbon;
use Carbon\Exceptions\UnitException;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Internal\Wallet\Actions\Transfer;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

use function Amp\Dns\query;

class FundsController extends ApiController {

    /**
     * 资金划转
     * @param Request $request
     * @param Transfer $transfer
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function transfer(Request $request, Transfer $transfer) {
        $request->validate([
            'to'=>['required', Rule::in([TransferEnums::WalletDerivative,TransferEnums::WalletSpot])],
            'uid'=>'required|numeric',
            'amount'=>'required|numeric',
        ]);

        $user = User::findOrFail($request->get('uid'));
        $transfer($request, $user);
        return $this->ok(true);
    }

    /**
     * 当日总计充值金额
     * @param Request $request
     * @return JsonResponse
     * @throws UnitException
     * @throws BindingResolutionException
     */
    public function summaryTodayDeposit(Request $request) {
        $startTime  = $request->get('start_time',null);
        $endTime    = $request->get('end_time',null);

        if(!$startTime){
            $startTime = Carbon::now()->startOfDay()->toDateTimeString();
        }
        if(!$endTime){
            $endTime = Carbon::now()->endOfDay()->toDateTimeString();
        }

        $query = UserDeposit::whereBetween('created_at',[$startTime,$endTime])
            ->where('status', FundsEnums::DepositStatusDone);
        $query->withGlobalScope('salesman_scope', new SalesmanScope());

        $depositTotal = $query->sum('usdt_value');
        return $this->ok($depositTotal);
    }

    /**
     * 当日总计提现成功金额
     * @param Request $request
     * @return JsonResponse
     * @throws UnitException
     * @throws BindingResolutionException
     */
    public function summaryTodayWithdraw(Request $request) {
        $startTime  = $request->get('start_time',null);
        $endTime    = $request->get('end_time',null);

        if(!$startTime){
            $startTime = Carbon::now()->startOfDay()->toDateTimeString();
        }
        if(!$endTime){
            $endTime = Carbon::now()->endOfDay()->toDateTimeString();
        }


        $query = UserWithdraw::whereBetween('created_at',[$startTime,$endTime])
            ->where('status', FundsEnums::WithdrawStatusDone);
        $query->withGlobalScope('salesman_scope', new SalesmanScope());
        $withdrawTotal = $query->sum('amount');
        return $this->ok($withdrawTotal);
    }

    /**
     * 入金列表
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function depositList(Request $request) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'status'=>['nullable', Rule::in(FundsEnums::DepositStatusMap)],
            'start_time'=>'nullable|date',
            'end_time'=>'nullable|date',
            'email'=>'nullable|string',
            'phone'=>'nullable|string',
            'salesman'=>'nullable|integer',
            'uid'=>'nullable|numeric',
        ]);
        $status     = $request->get('status',null);
        $startTime  = $request->get('start_time',null);
        $endTime    = $request->get('end_time',null);
        $phone      = $request->get('phone',null);
        $email      = $request->get('email',null);
        $uid        = $request->get('uid', null);

        $query = UserDeposit::with(['user','coin','address'=>function($query){
            return $query->with('platform');
        }]);

        $queryUids = [];
        if ($email) {
            $_query = User::select(['id'])->where('email','like','%'.$email.'%')->get();
            if ($_query->isEmpty()) {
                return $this->ok(listResp(null));
            }
            $queryUids = $_query->pluck('id')->toArray();
        }
        if ($phone) {
            $_query = User::select(['id'])->where('phone','like','%'.$phone.'%')->get();
            if ($_query->isEmpty()) {
                return $this->ok(listResp(null));
            }
            $queryUids = array_unique(array_merge($queryUids,$_query->pluck('id')->toArray() ));
        }
        if ($queryUids) {
            $query->whereIn('uid',$queryUids);
        }
        if ($uid !== null) {
            $query->where('uid', $uid);
        }

        if ($status) {
            $query->where('status', $status);
        }
        if ($startTime && $endTime && $startTime <= $endTime) {
            $query->whereBetween('created_at',[$startTime, $endTime]);
        }
        $query->withGlobalScope('salesman_scope', new SalesmanScope());
        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size'),['*'],null, $request->get('page'));
        return $this->ok(listResp($data));
    }

    /**
     * 提现列表
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function withdrawList(Request $request) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'status'=>['nullable', Rule::in(FundsEnums::WithdrawStatusMap)],
            'audit_status'=>['nullable',Rule::in(FundsEnums::AuditStatusMap)],
            'start_time'=>'nullable|date',
            'end_time'=>'nullable|date',
            'email'=>'nullable|string',
            'phone'=>'nullable|string',
            'salesman'=>'nullable|integer',
            'uid'=>'nullable|numeric',
        ]);

        $status         = $request->get('status',null);
        $uid            = $request->get('uid', 0);
        $auditStatus    = $request->get('audit_status',null);
        $startTime      = $request->get('start_time',null);
        $endTime        = $request->get('end_time',null);
        $phone          = $request->get('phone',null);
        $email          = $request->get('email',null);

        $queryUids = [];
        if ($email) {
            $_query = User::select(['id'])->where('email','like','%'.$email.'%')->get();
            if ($_query->isEmpty()) {
                return $this->ok(listResp(null));
            }
            $queryUids = $_query->pluck('id')->toArray();
        }
        if ($phone) {
            $_query = User::select(['id'])->where('phone','like','%'.$phone.'%')->get();
            if ($_query->isEmpty()) {
                return $this->ok(listResp(null));
            }
            $queryUids = array_unique(array_merge($queryUids,$_query->pluck('id')->toArray() ));
        }

        $query = UserWithdraw::with(['user','coin']);
        if ($queryUids) {
            $query->whereIn('uid',$queryUids);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($startTime && $endTime && $startTime <= $endTime) {
            $query->whereBetween('created_at',[$startTime, $endTime]);
        }
        if ($uid) {
            $query->where('uid', $uid);
        }
        if ($auditStatus) {
            $query->where('audit_status', $auditStatus);
        }
        $query->withGlobalScope('salesman_scope', new SalesmanScope());
        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size'),['*'],null, $request->get('page'));
        return $this->ok(listResp($data));
    }

    /**
     * 审核提现
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function auditWithdraw(Request $request) {
        $request->validate([
            'id'=>'required|numeric',
            'audit'=>['required',Rule::in(FundsEnums::AuditStatusProcessPassed, FundsEnums::AuditStatusProcessRejected)],
            'reason'=>'nullable|string',
        ]);

        DB::transaction(function() use($request){
            $withdraw = UserWithdraw::query()->where('id',$request->get('id'))->lockForUpdate()->first();
            if (!$withdraw) {
                throw new LogicException(__('数据不正确'));
            }
            if ($withdraw->status != FundsEnums::AuditStatusProcessWaiting) {
                throw new LogicException(__('状态不正确'));
            }
            $reason = $request->get('reason','');
            $withdraw->audit_status = $request->get('audit');
            if ($reason) {
                $withdraw->reason = $reason;
            }
            $withdraw->save();

            AuditWithdraw::dispatch($withdraw);
            return true;
        });
        return $this->ok(true);
    }
}

