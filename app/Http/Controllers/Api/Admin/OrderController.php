<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderEnums;
use App\Http\Controllers\Api\ApiController;
use App\Models\Scopes\SalesmanScope;
use App\Models\User;
use App\Models\UserOrderFutures;
use App\Models\UserOrderSpot;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Internal\Order\Actions\CloseFuturesOrder;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use InvalidArgumentException;

/** @package App\Http\Controllers\Api\Admin */
class OrderController extends ApiController {


    /**
     * 现货订单
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function spotOrders(Request $request) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'spot_id'=>'nullable|numeric',
            'agent_code'=>'nullable|string',
            'salesman'=>'nullable|integer',
            'email'=>'nullable|string',
            'phone'=>'nullable|string',
            'uid'=>'nullable|numeric',
            'status'=>['nullable', Rule::in(OrderEnums::SpotTradeStatusMap)],
        ]);

        $uid = $request->get('uid',null);
        $spotId = $request->get('spot_id',null);
        $agentCode = $request->get('agent_code',null);
        $email = $request->get('email','');
        $phone = $request->get('phone','');
        $status = $request->get('status','');

        $query = UserOrderSpot::with(['symbol','user']);
        if ($spotId !== null) {
            $query->where('spot_id', $spotId);
        }
        if ($status) {
            $query->where('trade_status', $status);
        }

        $queryUids = [];
        if ($email) {
            $emailUser = User::select(['id'])->where('email','like','%'.$email.'%')->get();
            if ($emailUser->isEmpty()) {
                return $this->ok([]);
            }
            $queryUids = $emailUser->pluck('id')->toArray();
        }

        if ($phone) {
            $phoneUser = User::select(['id'])->where('phone','like','%'.$phone.'%')->get();
            if ($phoneUser->isEmpty()) {
                return $this->ok([]);
            }
            $queryUids = $phoneUser->pluck('id')->toArray();
        }
        if ($agentCode) {
            $agentUser = User::select(['id'])->where('parent_id', $agentCode)->get();
            if ($agentUser->isEmpty()) {
                return $this->ok([]);
            }
            $queryUids = $agentUser->pluck('id')->toArray();
        }
        if ($uid) {
            $query->where('uid', $uid);
        }
        if ($queryUids) {
            $query->whereIn('uid', $queryUids);
        }
        $query->withGlobalScope('salesman_scope', new SalesmanScope());
        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size'),['*'],null, $request->get('page'));
        return $this->ok(listResp($data));
    }

    /**
     * 合约订单列表
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function futuresOrders(Request $request) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'futures_id'=>'nullable|numeric',
            'agent_code'=>'nullable|string',
            'salesman'=>'nullable|integer',
            'uid'=>'nullable|numeric',
            'email'=>'nullable|string',
            'phone'=>'nullable|string',
            'symbol'=>'nullable|string',
            'status'=>['nullable', Rule::in(OrderEnums::FuturesTradeStatusMap)],
        ]);

        $uid = $request->get('uid',null);
        $futuresId = $request->get('futures_id');
        $orderId = $request->get('order_id',null);
        $agentCode = $request->get('agent_code',null);
        $email = $request->get('email','');
        $phone = $request->get('phone','');
        $status = $request->get('status','');
        $symbol = $request->get('symbol','');

        $query = UserOrderFutures::with(['symbol','futures','user']);
        if ($futuresId) {
            $query->where('futures_id', $futuresId);
        }
        if ($status) {
            $query->where('trade_status', $status);
        }
        if ($orderId) {
            $query->where('id', $orderId);
        }
        if ($symbol) {
            $query->whereHas('symbol', function ($query) use ($symbol) {
                $query->where('symbol','like','%'.$symbol.'%');
            });
        }
        $queryUids = [];
        if ($email) {
            $emailUser = User::select(['id'])->where('email','like','%'.$email.'%')->get();
            if ($emailUser->isEmpty()) {
                return $this->ok([]);
            }
            $queryUids = $emailUser->pluck('id')->toArray();
        }
        if ($phone) {
            $phoneUser = User::select(['id'])->where('phone','like','%'.$phone.'%')->get();
            if ($phoneUser->isEmpty()) {
                return $this->ok([]);
            }
            $queryUids = $phoneUser->pluck('id')->toArray();
        }
        if ($agentCode) {
            $agentUser = User::select(['id'])->where('parent_id', $agentCode)->get();
            if ($agentUser->isEmpty()) {
                return $this->ok([]);
            }
            $queryUids = $agentUser->pluck('id')->toArray();
        }
        if ($uid) {
            $query->where('uid', $uid);
        }
        if ($queryUids) {
            $query->whereIn('uid', $queryUids);
        }
        $query->withGlobalScope('salesman_scope', new SalesmanScope());
        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size'),['*'],null, $request->get('page'));
        return $this->ok(listResp($data));
    }


    /**
     * 平仓用户订单
     * @param Request $request 
     * @param CloseFuturesOrder $closeFutures 
     * @return JsonResponse 
     * @throws BadRequestException 
     * @throws BindingResolutionException 
     */
    public function closeFuturesOrder(Request $request, CloseFuturesOrder $closeFutures) {
        $request->validate([
            'order_id' => 'required|numeric',
        ]);
        $orderId = $request->get('order_id');
        $closeFutures($orderId,0 , OrderEnums::FuturesCloseTypeForces);
        Log::info('admin close user order',[
            'order_id'=>$orderId,
            'admin_id'=>$request->user()->id,
        ]);
        return $this->ok(true);
    }
}
