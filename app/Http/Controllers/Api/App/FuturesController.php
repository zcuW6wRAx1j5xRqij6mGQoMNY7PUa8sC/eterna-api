<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\OrderEnums;
use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Internal\Order\Actions\AverageDown;
use Internal\Order\Actions\CancelFuturesOrder;
use Internal\Order\Actions\CloseFuturesOrder;
use Internal\Order\Actions\CreateFuturesOrder;
use Internal\Order\Actions\FuturesOrders;
use Internal\Order\Actions\ModifySLTP;
use Internal\Order\Payloads\FuturesOrderPayload;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class FuturesController extends ApiController {


    /**
     * 合约订单
     * @param Request $request
     * @param FuturesOrders $futuresOrders
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function orders(Request $request, FuturesOrders $futuresOrders) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'side'=>[Rule::in(OrderEnums::SideMap)],
            'status'=>[Rule::in(OrderEnums::FuturesTradeStatusMap)],
            'trade_type'=>[Rule::in([OrderEnums::TradeTypeLimit,OrderEnums::TradeTypeMarket])],
            'futures_id'=>'numeric',
            'symbol'=>'nullable|string',
        ]);
        return $this->ok($futuresOrders($request));
    }


    /**
     * 建仓
     * @param Request $request
     * @param CreateFuturesOrder $createFuturesOrder
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function create(Request $request, CreateFuturesOrder $createFuturesOrder) {
        $request->validate([
            'futures_id'=>'required|numeric',
            'side'=>['required', Rule::in(OrderEnums::SideMap)],
            'leverage'=>'required|numeric',
            'lots'=>'required|numeric',
            // 'trade_volume'=>'required|numeric',
            'trade_type'=>['required',Rule::in(OrderEnums::TradeTypeMap)],
            'margin_type'=>['required', Rule::in(OrderEnums::MarginTypeMap)],
            'price'=>'numeric',
            'sl'=>'nullable|numeric',
            'tp'=>'nullable|numeric',
        ]);

        // 判断用户等级对应的杠杆倍数
        // to
        // $levelID = $request->user()->level_id;
        // if (intval($levelID) === 1) {
        //     $levelID = 0;
        // }

        // $collect = [
        //     [25],
        //     [25, 50],
        //     [25, 50, 75],
        //     [25, 50, 75, 100],
        // ];
        


        $request->user()->checkFundsLock();
        
        $req = (new FuturesOrderPayload)->parseFromRequest($request);
        return $this->ok($createFuturesOrder($req));
    }

    /**
     * 取消挂单
     * @param Request $request
     * @param CancelFuturesOrder $cancelFuturesOrder
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function cancel(Request $request, CancelFuturesOrder $cancelFuturesOrder) {
        $request->validate([
            'order_id'=>'required|numeric',
        ]);
        $cancelFuturesOrder($request);
        return $this->ok(true);
    }


    /**
     * 平仓
     * @param Request $request
     * @param CloseFuturesOrder $closeFuturesOrder
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function close(Request $request, CloseFuturesOrder $closeFuturesOrder) {
        $request->validate([
            'order_id'=>'required|numeric'
        ]);
        $id = $request->get('order_id');
        return $this->ok($closeFuturesOrder($id));
    }

    /**
     * 补仓
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function averageDown(Request $request, AverageDown $averageDown) {
        $request->validate([
            'order_id'=>'required|numeric',
            'amount'=>'required|numeric',
        ]);
        return $this->ok($averageDown($request));
    }

    /**
     * 修改订单SL & TP
     * @param Request $request
     * @param ModifySLTP $modifySLTP
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function modifySLTP(Request $request, ModifySLTP $modifySLTP) {
        $request->validate([
            'order_id'=>'required|numeric',
            'tp'=>'numeric',
            'sl'=>'numeric',
        ]);
        return $this->ok($modifySLTP($request));
    }
}
