<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\OrderEnums;
use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Internal\Order\Actions\CloseDerivativeOrder;
use Internal\Order\Actions\CreateDerivativeOrder;
use Internal\Order\Actions\DerivativeOrders;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class DerivativeController extends ApiController {

    /**
     * 合约订单
     * @param Request $request
     * @param DerivativeOrders $derivativeOrders
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function orders(Request $request, DerivativeOrders $derivativeOrders) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'symbol_id'=>'numeric',
            'status'=>'nullable|numeric',
        ]);
        return $this->ok(listResp($derivativeOrders($request)));
    }

    /**
     * 建仓
     * @param Request $request
     * @param CreateDerivativeOrder $createDerivativeOrder
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function create(Request $request, CreateDerivativeOrder $createDerivativeOrder) {
        $request->validate([
            'derivative_id'=>'required|numeric',
            'leverage'=>['required', Rule::in(OrderEnums::DefaultLeverageMap)],
            'amount'=>'required|numeric',
            'side'=>['required', Rule::in(OrderEnums::SideMap)],
            'sl'=>'nullable|numeric',
            'tp'=>'nullable|numeric',
        ]);
        return $this->ok($createDerivativeOrder($request));
    }

    /**
     * 平仓
     * @param Request $request
     * @param CloseDerivativeOrder $closeDerivativeOrder
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function close(Request $request, CloseDerivativeOrder $closeDerivativeOrder) {
        $request->validate([
            'position_id'=>'required|numeric'
        ]);
        $id = $request->get('position_id');
        return $this->ok($closeDerivativeOrder($id));
    }
}
