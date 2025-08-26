<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\OrderEnums;
use App\Http\Controllers\Api\ApiController;
use App\Internal\Order\Actions\CloseOtcOrder;
use App\Internal\Order\Actions\CreateOtcOrder;
use App\Internal\Order\Actions\OtcOrders;
use App\Models\OtcProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class OtcController extends ApiController
{

    /**
     * otc产品列表
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function products(Request $request) {
        $products = OtcProduct::query()->with(['symbolCoin'])
            ->where('status', OrderEnums::StatusNormal)
            ->orderByDesc('created_at')->get();

        foreach ($products as $product) {
            $product['payment_method']  = ['SEPA-Überweisung'];
            $product['coin_name']       = $product->symbolCoin->name;
            unset($product->symbolCoin);
        }

        return $this->ok($products);
    }

    /**
     * 申请买入
     * @param Request $request
     * @param CreateOtcOrder $createOtcOrder
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function trade(Request $request, CreateOtcOrder $createOtcOrder) {
        $request->validate([
            'product_id'        => 'required|numeric',
            'quantity'          => 'required|string',
            'payment_method'    => 'required|string',
            'comments'          => 'nullable|string',
            'trade_type'        => ['required', Rule::in(OrderEnums::CommonTradeTypeMap)],
        ]);

        $request->user()->checkFundsLock();

        return $this->ok($createOtcOrder($request));
    }

    /**
     * 订单列表
     * @param Request $request
     * @param OtcOrders $otcOrders
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function orders(Request $request, OtcOrders $otcOrders) {
        $request->validate([
            'page'      => 'integer|nullable|min:1',
            'page_size' => 'integer|nullable|min:1|max:100',
            'status'    => ['nullable', Rule::in(OrderEnums::TradeStatusMap)],
        ]);
        return $this->ok($otcOrders($request));
    }

}
