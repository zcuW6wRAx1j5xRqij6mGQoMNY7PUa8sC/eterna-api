<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\OrderEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Internal\Order\Actions\CancelSpotOrder;
use Internal\Order\Actions\CreateSpotOrder;
use Internal\Order\Actions\SpotOrders;
use Internal\Order\Payloads\SpotOrderPayload;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;

class SpotController extends ApiController {

    /**
     * 现货订单列表
     * @param Request $request
     * @param SpotOrders $spotOrders
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function orders(Request $request, SpotOrders $spotOrders) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'side'=>[Rule::in(OrderEnums::SideMap)],
            'status'=>[Rule::in(OrderEnums::SpotTradeStatusMap)],
            'trade_type'=>[Rule::in([OrderEnums::TradeTypeLimit,OrderEnums::TradeTypeMarket])],
            'spot_id'=>'numeric',
        ]);
        return $this->ok($spotOrders($request));
    }

    /**
     * 新增现货订单
     * @param Request $request
     * @param CreateSpotOrder $createSpotOrder
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     */
    public function create(Request $request, CreateSpotOrder $createSpotOrder) {
        $request->validate([
            'spot_id'=>'required|numeric',
            'side'=>['required',Rule::in(OrderEnums::SideMap)],
            'quantity'=>'required|numeric',
            'trade_type'=>['required',Rule::in(OrderEnums::TradeTypeMap)],
            'price'=>'numeric',
        ]);

        $side = $request->get('side');
        $spotId = $request->get('spot_id');
        $user = $request->user();
    //    if ($side == OrderEnums::SideSell && $spotId == '50' && $user->id == '8089361') {
    //         // 2025-06-05 王飞需求  HMAI 货币, uid= 8089361 不能卖出
    //        throw new LogicException(__('The operation is unavailable at this time'));
    //    }

        $request->user()->checkFundsLock();

        return $this->ok($createSpotOrder((new SpotOrderPayload)->parseFromRequest($request)));
    }

    /**
     * 取消挂单
     * @param Request $request
     * @param CancelSpotOrder $cancelSpotOrder
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     */
    public function cancel(Request $request, CancelSpotOrder $cancelSpotOrder) {
        $request->validate([
            'order_id'=>'required|numeric',
        ]);
        $cancelSpotOrder($request);
        return $this->ok(true);
    }
}
