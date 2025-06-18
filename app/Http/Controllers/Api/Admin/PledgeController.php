<?php

namespace App\Http\Controllers\Api\Admin;

use App\Console\Commands\ClosePledgeOrder;
use App\Enums\CommonEnums;
use App\Enums\OrderEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Internal\Order\Actions\AuditPledgeOrder;
use App\Internal\Order\Actions\PledgeOrders;
use App\Internal\Order\Actions\RollbackPledgeOrder;
use App\Models\PledgeCoinConfig;
use App\Models\PledgeDurationConfig;
use App\Models\SymbolCoin;
use App\Models\UserWalletSpot;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use App\Models\UserOrderPledge;

class PledgeController extends ApiController
{


    /**
     * 获取质押币种可选项
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function coins(Request $request) {
        $config = SymbolCoin::pluck('name', 'id')->toArray();

        return $this->ok($config);
    }

    /**
     * 获取质押币种配置
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function coinConfig(Request $request) {
        $config = PledgeCoinConfig::select('pledge_coin_config.*', 'symbol_coins.name')
            ->join('symbol_coins','symbol_coins.id','=', 'pledge_coin_config.coin_id')
            ->get();

        return $this->ok($config);
    }

    /**
     * 新增质押币种配置
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function addCoinConfig(Request $request) {
        $request->validate([
            'coin_id' => 'required|numeric',
        ]);

        $coinId = $request->get('coin_id');

        $exists = SymbolCoin::where('id', $coinId)->exists();
        if (!$exists) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }

        $exists = PledgeCoinConfig::where('coin_id', $coinId)->exists();
        if ($exists) {
            return $this->ok();
        }

        $config = new PledgeCoinConfig();
        $config->coin_id    = $coinId;
        $config->status     = CommonEnums::Yes;
        $config->save();

        return $this->ok();
    }
    /**
     * 删除质押币种配置
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function dropCoinConfig(Request $request) {
        $request->validate([
            'coin_id' => 'required|numeric',
        ]);

        $coinId = $request->get('coin_id');
        PledgeCoinConfig::where('coin_id', $coinId)->delete();

        return $this->ok();
    }


    /**
     * 获取质押期限配置
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function durationConfig(Request $request) {
        return $this->ok(PledgeDurationConfig::pluck('days')->toArray());
    }

    /**
     * 新增质押期限配置
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function addDurationConfig(Request $request) {
        $request->validate([
            'days' => 'required|numeric',
        ]);

        $days = $request->get('days');
        $exists = PledgeDurationConfig::where('days', $days)->exists();
        if ($exists) {
            return $this->ok();
        }

        $config         = new PledgeDurationConfig();
        $config->days   = $days;
        $config->save();

        return $this->ok();
    }

    /**
     * 删除质押期限配置
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function dropDurationConfig(Request $request) {
        $request->validate([
            'days' => 'required|numeric',
        ]);

        $days = $request->get('days');
        PledgeDurationConfig::where('days', $days)->delete();

        return $this->ok();
    }

    /**
     * 质押订单列表
     * @param Request $request
     * @param PledgeOrders $pledgeOrders
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function orders(Request $request, PledgeOrders $pledgeOrders) {
        $request->validate([
            'page'      => 'integer|nullable|min:1',
            'page_size' => 'integer|min:1|max:100',
            'status'    => ['nullable', Rule::in(OrderEnums::PledgeTradeStatusMap)],
            'uid'       => 'integer|nullable',
            'coin_id'   => 'integer|nullable',
            'salesman'  => 'nullable|integer',
        ]);
        return $this->ok($pledgeOrders->auditList($request));
    }

    /**
     * 审核质押申请
     * @param Request $request
     * @param AuditPledgeOrder $auditPledgeOrder
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function audit(Request $request, AuditPledgeOrder $auditPledgeOrder) {
        $request->validate([
            'id'        => 'required|numeric',
            'status'    => [Rule::in([CommonEnums::Yes, CommonEnums::No])],
        ]);

        return $this->ok($auditPledgeOrder($request));
    }


    /**
     * 人工执行结算操作
     * @param Request $request
     * @param AuditPledgeOrder $auditPledgeOrder
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function settle(Request $request, AuditPledgeOrder $auditPledgeOrder) {
        $request->validate([
            'id' => 'required|numeric',
        ]);

        $order = UserOrderPledge::select('user_order_pledge.*', 'ws.id as usdc_wallet_id')
            ->join('user_wallet_spot AS ws', function ($join) {
                $join->on('user_order_pledge.uid', '=', 'ws.uid')
                    ->where('ws.coin_id', '=', CommonEnums::USDCCoinID);
            })
            ->where('user_order_pledge.status', OrderEnums::PledgeTradeStatusClosing)
            ->where('user_order_pledge.id', $request->get('id'))
            ->first();

        if(!$order){
            return $this->fail(__('The order does not exist or the status is incorrect'));
        }

        Log::info('人工执行质押订单', ['uid'=>$request->user()->id, 'order_id'=>$request->get('id')]);

        app(ClosePledgeOrder::class)->deal($order);

        return $this->ok();
    }


    /**
     * 回撤订单（从hold状态清退订单）
     * @param Request $request
     * @param RollbackPledgeOrder $rollbackPledgeOrder
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function rollback(Request $request, RollbackPledgeOrder $rollbackPledgeOrder) {
        $request->validate([
            'id' => 'required|numeric',
        ]);

        Log::info('人工回撤质押订单', ['uid'=>$request->user()->id, 'order_id'=>$request->get('id')]);
        $rollbackPledgeOrder($request->user()->id, $request->get('id', 0));

        return $this->ok();
    }


}
