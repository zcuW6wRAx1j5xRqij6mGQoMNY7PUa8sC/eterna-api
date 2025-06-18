<?php

namespace Internal\Order\Actions;

use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Enums\WalletFuturesFlowEnums;
use App\Events\NewFuturesOrder;
use App\Events\UpdateFuturesOrder;
use App\Events\UserFuturesBalanceUpdate;
use App\Exceptions\LogicException;
use App\Jobs\SendRefreshOrder;
use App\Models\User;
use App\Models\UserOrderFutures;
use App\Models\UserWalletFutures;
use App\Models\UserWalletFuturesFlow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Internal\Order\Payloads\FuturesOrderPayload;
use InvalidArgumentException;

class CreateFuturesOrder
{
    private TradeCalculator $calcu;

    public function __construct()
    {
        $this->calcu = new TradeCalculator();
    }

    public function __invoke(FuturesOrderPayload $payload)
    {
        return DB::transaction(function () use ($payload) {
            if ($this->allowChangeMarginType($payload->user, $payload->marginType)) {
                throw new LogicException(__('It is temporarily unavailable to change the margin type'));
            }

            // 创建订单
            $order = $this->makeOrder($payload);
            // 查看保证金是否足够
            $wallet = UserWalletFutures::where('uid', $payload->user->id)->lockForUpdate()->first();
            if (!$wallet) {
                // 不存在
                Log::error('failed to create futures order : no found user wallet', [
                    'uid' => $payload->user->id,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }

            $realMargin = $order->margin;
            if ($payload->fee) {
                $realMargin = bcadd($order->margin, $payload->fee, FundsEnums::DecimalPlaces);
            }

            // 扣除保证金
            $d = bcsub($wallet->balance, $realMargin, FundsEnums::DecimalPlaces);
            if ($d < 0) {
                throw new LogicException(__('Insufficient account balance'));
            }
            $before = $wallet->balance;
            $wallet->balance = $d;
            $wallet->lock_balance = bcadd($wallet->lock_balance, $order->margin, FundsEnums::DecimalPlaces);
            $wallet->save();


            if ($order->trade_type == OrderEnums::TradeTypeMarket) {
                // 计算保证金比例
                $order->margin_ratio = $this->calcu->calcuMarginRatio($order, $wallet, 0);
                // 计算强平价格
                $order->force_close_price = $this->calcu->calcuForceClosePrice($order, $order->open_price);
                $order->save();
            }

            // 增加流水信息
            $flow = new UserWalletFuturesFlow();
            $flow->uid = $payload->user->id;
            if ($order->trade_type == OrderEnums::TradeTypeMarket) {
                $flow->flow_type = WalletFuturesFlowEnums::FlowPositionMargin;
            } else {
                $flow->flow_type = WalletFuturesFlowEnums::FlowTypePostingOrder;
            }

            $flow->before_amount = $before;
            $flow->amount = $realMargin;
            $flow->after_amount = $wallet->balance;
            $flow->relation_id = $order->id;
            $flow->save();

            NewFuturesOrder::dispatch($order);
            UpdateFuturesOrder::dispatch($order);
            UserFuturesBalanceUpdate::dispatch($payload->user->id,floatTransferString($wallet->balance));
            return true;
        });
    }

    public function handelLimit(int $orderId, $latestPrice)
    {
        return DB::transaction(function () use ($orderId, $latestPrice) {

            $order = UserOrderFutures::where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                Log::error('合约处理限价单失败, 限价单状态不正确', ['id' => $orderId]);
                throw new LogicException('合约处理限价单失败, 没有找到订单数据');
            }
            if ($order->trade_status !== OrderEnums::SpotTradeStatusProcessing) {
                Log::error('合约处理限价单失败, 限价单状态不正确', ['id' => $orderId, 'status' => $order->trade_status]);
                throw new LogicException('合约处理限价单失败, 限价单状态不正确');
            }

            $order->market_price = $latestPrice;
            // $spread = $order->open_spread;
            $order->match_price = $order->market_price;

            // if ($order->side == OrderEnums::SideSell) {
            //     $order->match_price = $spread ? bcsub($order->market_price, $spread, FundsEnums::DecimalPlaces) : $order->market_price;
            // }

            $order->match_time = Carbon::now();
            $order->open_price = $order->match_price;
            $order->trade_status = OrderEnums::FuturesTradeStatusOpen;
            $order->volume = $this->calcu->calcuVolume($order->trade_volume, $order->match_price);
            $order->save();

            $wallet = UserWalletFutures::where('uid', $order->uid)->lockForUpdate()->first();
            if (!$wallet) {
                Log::error('合约处理限价单失败, 限价单状态不正确', ['id' => $orderId]);
                throw new LogicException('合约处理限价单失败, 没有找到用户钱包');
            }

            // 计算保证金比例
            $order->margin_ratio = $this->calcu->calcuMarginRatio($order, $wallet, 0);
            // 计算强平价格
            $order->force_close_price = $this->calcu->calcuForceClosePrice($order, $order->open_price);
            $order->save();

            SendRefreshOrder::dispatch(User::find($order->uid));
            UpdateFuturesOrder::dispatch($order);
            return true;
        });
    }


    public function makeOrder(FuturesOrderPayload $payload)
    {
        // 计算初始保证金
        // $margin = $this->calcu->calcuMargin($payload->tradeVolume, $payload->leverage);

        // 扣除掉手续费 放在在保证金里
        if ($payload->fee) {
            $payload->margin = bcsub($payload->margin, $payload->fee, FundsEnums::DecimalPlaces);
        }

        $order = new UserOrderFutures();
        $order->order_code = generateOrderCode('F');
        $order->uid = $payload->user->id;
        $order->margin_type = $payload->marginType;
        $order->margin = $payload->margin;

        $order->volume = 0;
        $order->lots = $payload->lots;
        $order->trade_volume = $payload->tradeVolume;

        $order->futures_id = $payload->futuresId;
        $order->symbol_id = $payload->symbol->id;
        $order->side = $payload->side;
        $order->trade_type = $payload->tradeType;
        $order->leverage = $payload->leverage;
        $order->price = $payload->price;
        $order->open_fee = $payload->fee;
        $order->trade_status = OrderEnums::FuturesTradeStatusProcessing;
        $order->open_spread = 0;

        if ($order->trade_type == OrderEnums::TradeTypeMarket) {
            $order->match_price = $payload->matchPrice;
            $order->market_price = $payload->marketPrice;
            $order->match_time = Carbon::now();
            $order->open_price = $payload->marketPrice;
            $order->trade_status = OrderEnums::FuturesTradeStatusOpen;
            $order->volume = $this->calcu->calcuVolume($order->trade_volume, $order->match_price);
        }

        if ($payload->sl > 0) {
            $order->sl = $payload->sl;
        }
        if ($payload->tp > 0) {
            $order->tp = $payload->tp;
        }
        $order->force_close_price = 0;

        $order->save();
        return $order;
    }

    /**
     * 是否准许更换保证金类型
     * @param User $user
     * @param string $newMarginType
     * @return bool
     * @throws InvalidArgumentException
     */
    public function allowChangeMarginType(User $user, string $newMarginType)
    {
        $queryMarginType = $newMarginType == OrderEnums::MarginTypeIsolated ? OrderEnums::MarginTypeCrossed : OrderEnums::MarginTypeIsolated;
        return UserOrderFutures::query()->where('uid', $user->id)->where('margin_type', $queryMarginType)->whereIn('trade_status', OrderEnums::CheckAllowChangeMarginTypeStatus)->exists();
    }
}
