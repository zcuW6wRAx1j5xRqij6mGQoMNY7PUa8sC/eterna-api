<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\OrderEnums;
use App\Http\Controllers\Api\ApiController;
use App\Internal\Order\Actions\CreatePledgeOrder;
use App\Internal\Order\Actions\PledgeOrders;
use App\Internal\Order\Payloads\PledgeOrderPayload;
use App\Models\PledgeCoinConfig;
use App\Models\PledgeDurationConfig;
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

class PledgeController extends ApiController
{

    /**
     * 质押配置
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function config(Request $request) {
        $coin       = PledgeCoinConfig::select('symbol_coins.name','symbol_coins.id', 'symbols.symbol')
            ->join('symbol_coins','symbol_coins.id','=', 'pledge_coin_config.coin_id')
            ->join('symbols', 'symbols.coin_id', '=', 'pledge_coin_config.coin_id')
            ->where('pledge_coin_config.status', 1)
            ->get();
        $duration   = PledgeDurationConfig::pluck('days')->toArray();
        $config     = [
            'coin'      => $coin,
            'duration'  => $duration,
        ];

        return $this->ok($config);
    }

    /**
     * 申请质押
     * @param Request $request
     * @param CreatePledgeOrder $createPledgeOrder
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function apply(Request $request, CreatePledgeOrder $createPledgeOrder) {
        $request->validate([
            'coin_id'   => 'required|numeric',
            'duration'  => 'integer|min:1',
        ]);

        $request->user()->checkFundsLock();

        $req = (new PledgeOrderPayload)->parseFromRequest($request);
        return $this->ok($createPledgeOrder($req));
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
        ]);
        return $this->ok($pledgeOrders($request));
    }

}
