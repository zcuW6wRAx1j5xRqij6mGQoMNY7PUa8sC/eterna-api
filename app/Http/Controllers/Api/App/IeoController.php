<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\CoinEnums;
use App\Enums\FundsEnums;
use App\Enums\IEOEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\IeoOrder;
use App\Models\IeoSymbol;
use App\Models\UserWalletSpot;
use App\Models\UserWalletSpotFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/** @package App\Http\Controllers\Api\App */
class IeoController extends ApiController
{


    public function list(Request $request)
    {
        $data = IeoSymbol::with(['symbol', 'coin'])->orderByDesc('created_at')->get();
        return $this->ok($data);
    }

    /**
     * 订单列表
     * @param Request $request 
     * @return JsonResponse 
     * @throws BadRequestException 
     * @throws InvalidArgumentException 
     * @throws BindingResolutionException 
     */
    public function orders(Request $request)
    {
        $request->validate([
            'page' => 'numeric',
            'page_size' => 'numeric',
            'status' => ['required', Rule::in(IEOEnums::OrderStatusQueryAll)],
        ]);

        $status = $request->get('status');
        $queryStatus = IEOEnums::translateStatus($status);
        if (!$queryStatus) {
            return $this->ok([]);
        }
        $query = IeoOrder::with(['user', 'ieo' => function ($query) {
            return $query->with('coin');
        }])->where('uid', $request->user()->id);
        if ($queryStatus) {
            $query->whereIn('status', $queryStatus);
        }
        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size', 15), ['*'], null, $request->get('page', 1));
        return $this->ok(listResp($data));
    }

    public function joinIn(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric',
            'amount' => 'required|numeric',
        ]);

        DB::transaction(function () use ($request) {
            $request->user()->checkFundsLock();

            $amount = parseNumber($request->get('amount'));
            if ($amount <= 0) {
                throw new LogicException(__('The amount is incorrect !'));
            }
            $symbol = IeoSymbol::find($request->get('id'));
            // 判断状态
            if ($symbol->status != IEOEnums::StatusPending) {
                throw new LogicException(__('The operation is unavailable at this time'));
            }
            // 判断金额
            if (bcsub($amount, $symbol->min_order_price, FundsEnums::DecimalPlaces) < 0) {
                throw new LogicException(__('The amount is incorrect'));
            }
            if (bcsub($symbol->max_order_price, $amount, FundsEnums::DecimalPlaces) < 0) {
                throw new LogicException(__('The amount is incorrect'));
            }

            $order = new IeoOrder();
            $order->uid = $request->user()->id;
            $order->ieo_id = $symbol->id;
            $order->unit_price = $symbol->unit_price;
            $order->total_amount = $amount;
            $order->quantity = bcdiv($amount, $symbol->unit_price, FundsEnums::DecimalPlaces);
            $order->save();
            return true;
        });
        return $this->ok(true);
    }
}
