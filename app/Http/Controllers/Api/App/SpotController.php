<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Internal\Order\Actions\CreateInstantExchangeOrder;
use App\Models\UserWalletSpotFlow;
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
            'quantity'=>'required|string',
            'trade_type'=>['required',Rule::in(OrderEnums::TradeTypeMap)],
            'price'=>'string',
        ]);

        // ULX 禁止卖出
        $side = $request->get('side');
        $spotId = $request->get('spot_id');
        if ($side == OrderEnums::SideSell && in_array($spotId, [55])) {
            throw new LogicException(__('The operation is unavailable at this time'));
        }


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

    /**
     * 闪兑订单
     * @param Request $request
     * @param CreateInstantExchangeOrder $createInstantExchangeOrder
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     * @throws ContainerExceptionInterface
     * @throws LogicException
     */
    public function instant(Request $request, CreateInstantExchangeOrder $createInstantExchangeOrder)
    {
        $request->validate([
            'from_coin_id' => 'required|integer|exists:symbol_coins,id',
            'to_coin_id'   => 'required|integer|exists:symbol_coins,id',
            'quantity'     => 'required|numeric|min:0.0001',
        ]);

        $fromCoinID = $request->get('from_coin_id');
        $toCoinID   = $request->get('to_coin_id');

        // ULX 禁止闪兑
        if ($fromCoinID == '543' || $toCoinID == '543') {
            throw new LogicException(__('The operation is unavailable at this time'));
        }
        

        $quantity   = parseNumber($request->get('quantity'));
        if ($quantity <= 0) {
            throw new LogicException(__('The amount is incorrect'));
        }
        if($fromCoinID == $toCoinID) {
            throw new LogicException(__('The operation is unavailable at this time'));
        }

        $result = $createInstantExchangeOrder($fromCoinID, $quantity, $toCoinID, $request->user()->id);
        return $this->ok($result);
    }

    /**
     * 闪兑记录
     * @param Request $request
     * @return JsonResponse
     */
    public function instantLogs(Request $request)
    {
        $request->validate([
            'page'       => 'numeric',
            'page_size'  => 'numeric',
        ]);

        $result = UserWalletSpotFlow::query()->with(['coin'])
            ->where('uid', $request->user()->id)
            ->where('flow_type', SpotWalletFlowEnums::FlowTypeInstantExchangeAdd)
            ->orderByDesc('created_at')
            ->paginate($request->get('page_size'),['*'],null, $request->get('page'));

        return $this->ok($result);
    }
}
