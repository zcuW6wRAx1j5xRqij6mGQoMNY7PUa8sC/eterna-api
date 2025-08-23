<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\CoinEnums;
use App\Enums\CommonEnums;
use App\Enums\FinancialEnums;
use App\Enums\FundsEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\Financial;
use App\Models\UserOrderFinancial;
use App\Models\UserWalletSpot;
use App\Models\UserWalletSpotFlow;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/** @package App\Http\Controllers\Api\App */
class FinancialController extends ApiController
{
    /**
     * 产品列表
     * @param Request $request 
     * @return JsonResponse 
     * @throws InvalidArgumentException 
     * @throws BadRequestException 
     * @throws BindingResolutionException 
     */
    public function products(Request $request)
    {
        $request->validate([
            'page' => 'numeric',
            'page_size' => 'numeric',
            'category' => ['nullable', Rule::in(FinancialEnums::CategoryAll)],
        ]);

        $query = Financial::query()->where('status', CommonEnums::Yes)->orderBy('sort');
        $category = $request->get('category');
        if ($category !== null) {
            $query->where('category', $category);
        }
        return $this->ok(listResp($query->paginate($request->get('page_size', 15))));
    }
    /**
     * 产品详情
     * @param Request $request 
     * @return JsonResponse 
     * @throws BadRequestException 
     * @throws BindingResolutionException 
     * @throws NotFoundExceptionInterface 
     * @throws ContainerExceptionInterface 
     * @throws LogicException 
     */
    public function productDetail(Request $request) {
        $request->validate([
            'id' => 'required|numeric',
        ]);

        $fiancial = Financial::find($request->get('id'));
        if (!$fiancial) {
            throw new LogicException(__('product not found'));
        }
        return $this->ok($fiancial);
    }


    /**
     * 订单列表
     * @param Request $request 
     * @return JsonResponse 
     * @throws InvalidArgumentException 
     * @throws BadRequestException 
     * @throws BindingResolutionException 
     */
    public function orders(Request $request)
    {
        $request->validate([
            'page' => 'numeric',
            'page_size' => 'numeric',
            'status' => ['nullable', Rule::in(FinancialEnums::StatusAll)],
        ]);
        $query = UserOrderFinancial::with(['financial'])->where('uid', $request->user()->id)->orderByDesc('created_at');
        $status = $request->get('status');
        if ($status !== null) {
            $query->where('status', $status);
        }
        return $this->ok(listResp($query->paginate($request->get('page_size', 15))));
    }

    /**
     * 买入理财
     * @param Request $request 
     * @return JsonResponse 
     * @throws BindingResolutionException 
     */
    public function buy(Request $request)
    {
        $request->validate([
            'financial_id' => 'required|numeric',
            'amount' => 'required|string',
            'duration' => 'required|numeric',
        ]);

        DB::transaction(function () use ($request) {
            $amount = $request->get('amount');
            $amount = parseNumber($amount);
            if ($amount <= 0) {
                throw new LogicException(__('invalid amount'));
            }
            $duration = $request->get('duration');
            $financial = Financial::query()->where('id', $request->get('financial_id'))->where('status', CommonEnums::Yes)->first();
            if (!$financial) {
                throw new LogicException(__('product not found'));
            }
            if ($financial->min_amount > 0 && $amount < $financial->min_amount) {
                throw new LogicException(__('incorrect amount'));
            }
            if ($financial->max_amount > 0 && $amount > $financial->max_amount) {
                throw new LogicException(__('incorrect amount'));
            }
            if (!in_array($duration, $financial->duration)) {
                throw new LogicException(__('incorrect duration'));
            }


            // 检查现货可用余额
            $wallet = UserWalletSpot::where('uid', $request->user()->id)->where('coin_id', CoinEnums::DefaultUSDTCoinID)->lockForUpdate()->first();
            if (!$wallet) {
                throw new LogicException(__('insufficient balance'));
            }
            $d = bcsub($wallet->amount, $amount, FundsEnums::DecimalPlaces);
            if ($d < 0) {
                throw new LogicException('用户可用余额不足');
            }
            $before = $wallet->amount;
            $wallet->amount = $d;
            $wallet->lock_amount = bcadd($wallet->lock_amount, $amount, FundsEnums::DecimalPlaces);
            $wallet->save();

            $flow = new UserWalletSpotFlow();
            $flow->uid = $request->user()->id;
            $flow->coin_id = CoinEnums::DefaultUSDTCoinID;
            $flow->flow_type = SpotWalletFlowEnums::FlowTypeFinancial;
            $flow->before_amount = $before;
            $flow->amount = $amount;
            $flow->after_amount = $wallet->amount;
            $flow->relation_id = 0;
            $flow->save();

            $order = new UserOrderFinancial();
            $order->financial_id = $financial->id;
            $order->uid = $request->user()->id;
            $order->duration = $duration;
            $order->amount = $amount;
            $order->daily_rate = $financial->min_daily_rate;
            $order->total_rate = bcdiv(bcmul($financial->min_daily_rate, $duration, FundsEnums::DecimalPlaces), 100, FundsEnums::DecimalPlaces);
            $order->expected_total_profit = bcdiv(
                bcmul(bcmul($amount, $financial->min_daily_rate, FundsEnums::DecimalPlaces), $duration, FundsEnums::DecimalPlaces),
                100,
                FundsEnums::DecimalPlaces
            );
            $order->start_at = Carbon::now();
            $order->end_at = Carbon::now()->addDays($duration);
            $order->status = FinancialEnums::StatusPending;
            $order->save();

            return true;
        });
        return $this->ok(true);
    }


    /**
     * 提前赎回
     * @param Request $request 
     * @return JsonResponse 
     * @throws BindingResolutionException 
     */
    public function redeem(Request $request)
    {
        $request->validate([
            'order_id' => 'required|numeric',
        ]);

        DB::transaction(function () use ($request) {
            $order = UserOrderFinancial::where('id', $request->get('order_id'))
                ->where('uid', $request->user()->id)
                ->where('status', FinancialEnums::StatusPending)->lockForUpdate()->first();
            if (!$order) {
                throw new LogicException(__('order not found'));
            }
            $financial = $order->financial;
            if ($financial->category === FinancialEnums::CategoryFixed) {
                throw new LogicException(__('fixed product cannot be redeemed'));
            }

            // 退回本金 - 扣除罚金
            $totalAmount = $order->amount;
            $penaltyAmount = 0;
            if ($financial->penalty_rate > 0) {
                $penaltyAmount = bcdiv(
                    bcmul($order->amount, $financial->penalty_rate, FundsEnums::DecimalPlaces),
                    100,
                    FundsEnums::DecimalPlaces
                );
                $totalAmount = bcsub($totalAmount, $penaltyAmount, FundsEnums::DecimalPlaces);
            }

            // 检查现货可用余额
            $wallet = UserWalletSpot::where('uid', $request->user()->id)->where('coin_id', CoinEnums::DefaultUSDTCoinID)->lockForUpdate()->first();
            if (!$wallet) {
                throw new LogicException(__('insufficient balance'));
            }

            $before = $wallet->amount;
            $wallet->amount = bcadd($wallet->amount, $totalAmount, FundsEnums::DecimalPlaces);
            $d = bcsub($wallet->lock_amount, $order->amount, FundsEnums::DecimalPlaces);
            if ($d < 0) {
                Log::error('赎回失败, 用户锁定金额异常', ['uid' => $request->user()->id, 'order_id' => $order->id]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            $wallet->lock_amount = $d;
            $wallet->save();

            $flow = new UserWalletSpotFlow();
            $flow->uid = $request->user()->id;
            $flow->coin_id = CoinEnums::DefaultUSDTCoinID;
            $flow->flow_type = SpotWalletFlowEnums::FlowTypeFinancialSettle;
            $flow->before_amount = $before;
            $flow->amount = $totalAmount;
            $flow->after_amount = $wallet->amount;
            $flow->relation_id = 0;
            $flow->save();

            $order->status = FinancialEnums::StatusSettled;
            $order->settled_at = Carbon::now();
            $order->penalty_amount = $penaltyAmount;
            $order->save();

            return true;
        });
        return $this->ok(true);
    }
}
