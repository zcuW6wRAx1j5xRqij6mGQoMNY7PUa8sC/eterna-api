<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CoinEnums;
use App\Enums\CommonEnums;
use App\Enums\FundsEnums;
use App\Enums\IEOEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Models\IeoOrder;
use App\Models\IeoSymbol;
use App\Models\Scopes\SalesmanScope;
use App\Models\Symbol;
use App\Models\User;
use App\Models\UserWalletSpot;
use App\Models\UserWalletSpotFlow;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Admin\Ieo\AddOrderRequest;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

use function Amp\Dns\query;
use function Amp\Promise\wait;

/** @package App\Http\Controllers\Api\Admin */
class IeoController extends ApiController {

    public function list(Request $request)
    {
        $data = IeoSymbol::with(['symbol', 'coin'])->orderByDesc('created_at')->get();
        return $this->ok($data);
    }

    public function add(Request $request)
    {
        $request->validate([
            'ieo_name'         => 'required|string',
            'symbol_id'        => 'required|numeric',
            'total_supply'     => 'required|numeric',
            'unit_price'       => 'required|numeric',
            'min_order_price'  => 'required|numeric',
            'max_order_price'  => 'required|numeric',
            'processing'       => 'nullable|numeric',
            'order_start_time' => 'required|date',
            'order_end_time'   => 'required|date',
            'public_time'      => 'required|date',
            'release_time'     => 'required|date',
        ]);

        $ieo = new IeoSymbol();
        if (Carbon::now()->isBefore(Carbon::parse($request->get('order_start_time')))) {
            $ieo->status = 0;
        } else {
            if (Carbon::now()->isBefore(Carbon::parse($request->get('order_end_time')))) {
                $ieo->status = 1;
            } else {
                if (Carbon::now()->isBefore(Carbon::parse($request->get('release_time')))) {
                    $ieo->status = 2;
                }
            }
            if (Carbon::now()->isAfter(Carbon::parse($request->get('release_time')))) {
                $ieo->status = 3;
            }
        }

        $sameName = IeoSymbol::where('ieo_name', $request->get('ieo_name'))->exists();
        if ($sameName) {
            throw new LogicException('名称已存在');
        }

        $minOrderPrice = $request->get('min_order_price');
        $maxOrderPrice = $request->get('max_order_price');
        if ($minOrderPrice <= 0 || $maxOrderPrice <= 0) {
            throw new LogicException('价格区间不正确');
        }
        if ($minOrderPrice >= $maxOrderPrice) {
            throw new LogicException('价格区间不正确');
        }
        $startTime = $request->get('order_start_time');
        $endTime   = $request->get('order_end_time');
        if ($startTime >= $endTime) {
            throw new LogicException('认购时间不正确');
        }
        $releaseTime = $request->get('release_time');
        if ($releaseTime <= $endTime) {
            throw new LogicException('中签时间不正确');
        }
        $publicTime = $request->get('public_time');
        if ($publicTime <= $releaseTime) {
            throw new LogicException('上市时间不正确');
        }

        $symbol = Symbol::find($request->get('symbol_id'));
        if (!$symbol) {
            throw new LogicException('交易对不正确');
        }
        if (!$symbol->coin_id) {
            throw new LogicException('交易对不正确');
        }
        $totalSupply = $request->get('total_supply');
        $processing  = $request->get('processing');
        if ($processing !== null && $processing > 0) {
            $ieo->processing = $processing;
            if ($totalSupply > 0) {
                $ieo->forecast_earnings = bcmul(bcdiv($processing, $totalSupply, FundsEnums::DecimalPlaces), 100, FundsEnums::DecimalPlaces);
            }
        }

        $ieo->ieo_name         = $request->get('ieo_name');
        $ieo->symbol_id        = $symbol->id;
        $ieo->coin_id          = $symbol->coin_id;
        $ieo->total_supply     = $totalSupply;
        $ieo->unit_price       = $request->get('unit_price');
        $ieo->min_order_price  = $request->get('min_order_price');
        $ieo->max_order_price  = $request->get('max_order_price');
        $ieo->order_start_time = $request->get('order_start_time');
        $ieo->order_end_time   = $request->get('order_end_time');
        $ieo->public_time      = $request->get('public_time');
        $ieo->release_time     = $request->get('release_time');

        $ieo->save();
        return $this->ok(true);
    }

    public function edit(Request $request)
    {
        $request->validate([
            'id'               => 'required|numeric',
            'ieo_name'         => 'required|string',
            'total_supply'     => 'required|numeric',
            'unit_price'       => 'required|numeric',
            'min_order_price'  => 'required|numeric',
            'max_order_price'  => 'required|numeric',
            'processing'       => 'nullable|numeric',
            'order_start_time' => 'required|date',
            'order_end_time'   => 'required|date',
            'public_time'      => 'required|date',
            'release_time'     => 'required|date',
        ]);
        $ieo = IeoSymbol::find($request->get('id'));
        if (!$ieo) {
            throw new LogicException('数据不正确');
        }

        $minOrderPrice = $request->get('min_order_price');
        $maxOrderPrice = $request->get('max_order_price');
        if ($minOrderPrice <= 0 || $maxOrderPrice <= 0) {
            throw new LogicException('价格区间不正确');
        }
        if ($minOrderPrice >= $maxOrderPrice) {
            throw new LogicException('价格区间不正确');
        }
        $startTime = $request->get('order_start_time');
        $endTime   = $request->get('order_end_time');
        if ($startTime >= $endTime) {
            throw new LogicException('认购时间不正确');
        }
        $releaseTime = $request->get('release_time');
        if ($releaseTime <= $endTime) {
            throw new LogicException('中签时间不正确');
        }
        $publicTime = $request->get('public_time');
        if ($publicTime <= $releaseTime) {
            throw new LogicException('上市时间不正确');
        }

        $totalSupply = $request->get('total_supply');
        $processing  = $request->get('processing');
        if ($processing !== null && $processing > 0) {
            $ieo->processing = $processing;
            if ($totalSupply > 0) {
                $ieo->forecast_earnings = bcmul(bcdiv($processing, $totalSupply, FundsEnums::DecimalPlaces), 100, FundsEnums::DecimalPlaces);
            }
        }

        $ieo->ieo_name         = $request->get('ieo_name');
        $ieo->total_supply     = $totalSupply;
        $ieo->unit_price       = $request->get('unit_price');
        $ieo->min_order_price  = $request->get('min_order_price');
        $ieo->max_order_price  = $request->get('max_order_price');
        $ieo->order_start_time = $request->get('order_start_time');
        $ieo->order_end_time   = $request->get('order_end_time');
        $ieo->public_time      = $request->get('public_time');
        $ieo->release_time     = $request->get('release_time');

        $ieo->save();
        return $this->ok(true);
    }

    public function orders(Request $request)
    {
        $request->validate([
            'page'      => 'required|numeric',
            'page_size' => 'required|numeric',
            'uid'       => 'nullable|numeric',
            'ieo_id'    => 'nullable|numeric',
            'email'     => 'nullable|string',
            'phone'     => 'nullable|string',
            'status'    => ['nullable', Rule::in(IEOEnums::OrderStatus)],
            'salesman'  => 'nullable|integer',
        ]);
        $query  = IeoOrder::with(['user', 'ieo']);
        $uid    = $request->get('uid');
        $ieoId  = $request->get('id');
        $email  = $request->get('email');
        $phone  = $request->get('phone');
        $status = $request->get('status');

        if ($ieoId) {
            $query->where('ieo_id', $ieoId);
        }
        if ($uid) {
            $query->where('uid', $uid);
        }
        if ($email) {
            $queryEmail = User::select(['id'])->where('email', 'like', '%' . $email . '%')->get();
            if ($queryEmail->isEmpty()) {
                return $this->ok([]);
            }
            $query->whereIn('uid', $queryEmail->pluck('id')->toArray());
        }
        if ($phone) {
            $queryPhone = User::select(['id'])->where('phone', 'like', '%' . $phone . '%')->get();
            if ($queryPhone->isEmpty()) {
                return $this->ok([]);
            }
            $query->whereIn('uid', $queryPhone->pluck('id')->toArray());
        }
        if ($status !== null) {
            $query->where('status', $status);
        }
        $query->withGlobalScope('salesman_scope', new SalesmanScope());
        $data = $query->orderByDesc('created_at')->paginate($request->get('page_size'), ['*'], null, $request->get('page'));
        return $this->ok(listResp($data));
    }

    /**
     * 添加订单
     *
     * 此函数用于处理添加新订单的请求。它首先验证请求的数据是否满足特定条件，
     * 然后在数据库中创建一个新的订单记录。
     *
     * @param AddOrderRequest $request 包含添加订单所需信息的请求对象
     *
     * @return  JsonResponse 添加订单操作的结果
     */
    public function addOrder(AddOrderRequest $request): JsonResponse
    {
        // 使用数据库事务来确保订单创建过程中的数据一致性
        DB::transaction(function () use ($request) {
            // 从请求中提取必要的数据
            $data = $request->only(['ieo_id', 'uid', 'amount']);
            // 确保订单金额为正数
            $amount = abs($data['amount']);
            // 获取IEO符号信息
            $symbol = IeoSymbol::find($data['ieo_id']);
            // // 检查IEO状态是否允许认购
            // if ($symbol->status != IEOEnums::StatusPending) {
            //     throw new LogicException('当前状态无法认购');
            // }
            // // 判断时间
            // $time = time();
            // if ($time < strtotime($symbol->order_start_time) || strtotime($symbol->order_end_time) < $time) {
            //     throw new LogicException(__('The time is incorrect'));
            // }
            // 判断金额
            if (bcsub($amount, $symbol->min_order_price, FundsEnums::DecimalPlaces) < 0) {
                throw new LogicException(__('The amount is incorrect'));
            }
            if (bcsub($symbol->max_order_price, $amount, FundsEnums::DecimalPlaces) < 0) {
                throw new LogicException(__('The amount is incorrect'));
            }

            // 创建并保存新的IEO订单
            $order               = new IeoOrder();
            $order->uid          = $data['uid'];
            $order->ieo_id       = $data['ieo_id'];
            $order->unit_price   = $symbol->unit_price;
            $order->total_amount = $amount;
            $order->quantity     = bcdiv($amount, $symbol->unit_price, FundsEnums::DecimalPlaces);
            $order->save();
            return true;
        });
        return $this->ok(true);
    }


    public function editOrders(Request $request)
    {
        // 操作废弃
        return $this->ok(false);
        $request->validate([
            'id'           => 'required|numeric',
            'total_amount' => 'required|numeric',
        ]);
        $order = IeoOrder::find($request->get('id'));
        if (!$order) {
            throw new LogicException('数据不正确');
        }
        if (!in_array($order->status, [IEOEnums::OrderStatusProcessing, IEOEnums::OrderStatusOrder])) {
            throw new LogicException('当前状态无法修改');
        }
        $symbols = IeoSymbol::find($order->ieo_id);
        if (!$symbols) {
            throw new LogicException('数据不正确.');
        }
        $totalAmount = $request->get('total_amount');
        $totalAmount = abs($totalAmount);

        $order->quantity     = bcdiv($totalAmount, $symbols->unit_price, FundsEnums::DecimalPlaces);
        $order->total_amount = $totalAmount;
        $order->save();
        return $this->ok(true);
    }

    /**
     * 认购
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function subscribed(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric',
        ]);

        DB::transaction(function () use ($request) {
            $order = IeoOrder::find($request->get('id'));
            if (!$order) {
                throw new LogicException('数据不正确');
            }
            if ($order->status != IEOEnums::OrderStatusProcessing) {
                throw new LogicException('当前状态无法修改');
            }
            if ($order->result_quantity <= 0) {
                throw new LogicException('当前订单没有中签数量');
            }
            if (bcsub($order->subscribed_amount, $order->result_total_amount, FundsEnums::DecimalPlaces) >= 0) {
                throw new LogicException('认缴已完成, 没有需要认缴资金了');
            }

            // 此次扣除金额
            $finished = true;
            $amount   = bcsub($order->result_total_amount, $order->subscribed_amount, FundsEnums::DecimalPlaces);
            if ($amount <= 0) {
                throw new LogicException('当前订单没有需要认缴资金了');
            }

            // 检查现货可用余额
            $wallet = UserWalletSpot::where('uid', $order->uid)->where('coin_id', CoinEnums::DefaultUSDTCoinID)->lockForUpdate()->first();
            if (!$wallet) {
                throw new LogicException('用户钱包数据不正确, 请联系管理员');
            }
            if ($wallet->amount <= 0) {
                throw new LogicException('当前钱包没有可用余额');
            }
            $d = bcsub($wallet->amount, $amount, FundsEnums::DecimalPlaces);
            if ($d < 0) {
                // 此次余额不足 , 有多少扣多少
                $finished = false;
                $amount   = $wallet->amount;
                $d        = 0;
            }


            $before         = $wallet->amount;
            $wallet->amount = $d;
            if (!$finished) {
                $wallet->lock_amount  = bcadd($wallet->lock_amount, $amount, FundsEnums::DecimalPlaces);
                $order->locked_amount = bcadd($order->locked_amount, $amount, FundsEnums::DecimalPlaces);
            }
            $wallet->save();

            // 记录冻结金额流水
            $flow                = new UserWalletSpotFlow();
            $flow->uid           = $order->uid;
            $flow->coin_id       = CoinEnums::DefaultUSDTCoinID;
            $flow->flow_type     = SpotWalletFlowEnums::FlowTypeIEO;
            $flow->before_amount = $before;
            $flow->amount        = $amount;
            $flow->after_amount  = $wallet->amount;
            $flow->relation_id   = 0;
            $flow->save();

            $order->subscribed_amount = bcadd($order->subscribed_amount, $amount, FundsEnums::DecimalPlaces);
            $order->save();

            // 先锁定用户账户
            $user             = User::find($order->uid);
            $user->funds_lock = CommonEnums::Yes;
            $user->save();

            if (!$finished) {
                return true;
            }

            $order->status = IEOEnums::OrderStatusCompleted;
            // 解冻所有冻结
            if ($order->locked_amount > 0) {
                $wallet->lock_amount  = bcsub($wallet->lock_amount, $order->locked_amount, FundsEnums::DecimalPlaces);
                $order->locked_amount = 0;
            }
            $wallet->save();
            $order->save();


            // 新币发放
            $newCoinwallet = UserWalletSpot::where('uid', $order->uid)->where('coin_id', $order->ieo->coin_id)->lockForUpdate()->first();
            if (!$newCoinwallet) {
                $n          = new UserWalletSpot();
                $n->uid     = $order->uid;
                $n->coin_id = $order->ieo->coin_id;
                $n->save();
                $newCoinwallet = UserWalletSpot::where('uid', $order->uid)->where('coin_id', $order->ieo->coin_id)->lockForUpdate()->first();
            }
            if (!$newCoinwallet) {
                throw new LogicException('用户钱包数据不正确, 请联系管理员');
            }


            $newCoinwalletBefore   = $newCoinwallet->amount;
            $newCoinwallet->amount = bcadd($newCoinwallet->amount, $order->result_quantity, FundsEnums::DecimalPlaces);
            $newCoinwallet->save();

            $quoteFlow                = new UserWalletSpotFlow();
            $quoteFlow->uid           = $request->user()->id;
            $quoteFlow->coin_id       = $order->ieo->coin_id;
            $quoteFlow->flow_type     = SpotWalletFlowEnums::FlowTypeIEOSettlement;
            $quoteFlow->before_amount = $newCoinwalletBefore;
            $quoteFlow->amount        = $order->result_quantity;
            $quoteFlow->after_amount  = $newCoinwallet->amount;
            $quoteFlow->relation_id   = 0;
            $quoteFlow->save();

            // 判断该用户是否所有IEO订单都认缴完成, 只要存在认购的 , 不解冻账户
            $exists = IeoOrder::where('uid', $order->uid)->where('status', IEOEnums::OrderStatusProcessing)->get();
            if ($exists->isNotEmpty()) {
                return true;
            }
            $user->funds_lock = CommonEnums::No;
            $user->save();

            return true;
        });
        return $this->ok(true);
    }

    /**
     * 中签
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function publicResult(Request $request)
    {
        $request->validate([
            'id'                  => 'required|numeric',
            'result_total_amount' => 'required|numeric',
        ]);

        DB::transaction(function () use ($request) {
            $order = IeoOrder::find($request->get('id'));
            if (!$order) {
                throw new LogicException('数据不正确');
            }
            if ($order->status > IEOEnums::OrderStatusProcessing) {
                throw new LogicException('当前状态无法修改');
            }
            $symbols = IeoSymbol::find($order->ieo_id);
            if (!$symbols) {
                throw new LogicException('数据不正确.');
            }
            if ($symbols->status != IEOEnums::StatusProcessing) {
                throw new LogicException('未到中签时间, 无法操作');
            }
            if ($order->subscribed_amount > 0) {
                throw new LogicException('当前订单已认缴, 无法操作');
            }

            $amount = abs($request->get('result_total_amount'));
            if ($amount <= 0) {
                throw new LogicException('中签数量不正确');
            }
            if (bcsub($order->total_amount, $amount, FundsEnums::DecimalPlaces) < 0) {
                throw new LogicException('中签数量不正确, 当前订单最大中签USDT金额 : ' . $order->total_amount);
            }
            // 修改IEO订单
            $settlementNewCoinAmount    = bcdiv($amount, $order->unit_price, FundsEnums::DecimalPlaces);
            $order->result_unit_price   = $order->unit_price;
            $order->result_total_amount = $amount;
            $order->result_quantity     = $settlementNewCoinAmount;
            $order->result_time         = Carbon::now();
            $order->subscribed_amount   = 0;
            $order->status              = IEOEnums::OrderStatusProcessing;
            $order->save();
            return true;
        });
        return $this->ok(true);
    }
}
