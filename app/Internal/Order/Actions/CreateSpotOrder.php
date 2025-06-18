<?php

namespace Internal\Order\Actions;

use App\Enums\CoinEnums;
use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Events\NewSpotOrder;
use App\Exceptions\LogicException;
use App\Jobs\SendRefreshOrder;
use App\Models\User;
use App\Models\UserOrderSpot;
use App\Models\UserWalletSpot;
use App\Models\UserWalletSpotFlow;
use Carbon\Carbon;
use DivisionByZeroError;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Internal\Order\Payloads\SpotOrderPayload;
use InvalidArgumentException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;

/** @package Internal\Order\Actions */
class CreateSpotOrder
{

    public function __invoke(SpotOrderPayload $spotOrderPayload)
    {
        return DB::transaction(function () use ($spotOrderPayload) {
            $order = $spotOrderPayload->tradeType == OrderEnums::TradeTypeMarket ? $this->market($spotOrderPayload) : $this->limit($spotOrderPayload);
            NewSpotOrder::dispatch($order);
            return true;
        });
    }

    /**
     * 限价
     * @param SpotOrderPayload $payload
     * @return true
     * @throws InvalidArgumentException
     * @throws InvalidCastException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     */
    public function limit(SpotOrderPayload $payload)
    {
        $order = $this->makeOrder($payload);
        $this->liquidationSrcCoin($order);
        return $order;
    }

    /**
     * 市价
     * @param SpotOrderPayload $payload
     * @return true
     * @throws DivisionByZeroError
     * @throws InvalidArgumentException
     * @throws InvalidCastException
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     */
    public function market(SpotOrderPayload $payload)
    {
        $order = $this->makeOrder($payload);
        $this->liquidationSrcCoin($order);
        $this->liquidationTargeCoin($order);
        return $order;
    }

    /**
     * 创建订单
     * @param SpotOrderPayload $payload 
     * @return UserOrderSpot 
     * @throws InvalidArgumentException 
     * @throws InvalidCastException 
     */
    public function makeOrder(SpotOrderPayload $payload)
    {
        $order = new UserOrderSpot();
        $order->order_code = generateOrderCode('S');
        $order->uid = $payload->user->id;
        $order->spot_id = $payload->spotId;
        $order->symbol_id = $payload->symbol->id;
        $order->side = $payload->side;
        $order->trade_type = $payload->tradeType;
        $order->spread = abs($payload->spread);
        $order->fee = 0;

        $order->base_asset = $payload->baseAsset;
        $order->quote_asset = $payload->quoteAsset;
        $order->trade_volume = $payload->tradeVolume;

        if ($payload->tradeType == OrderEnums::TradeTypeLimit) {
            $order->price = $payload->price;
            $order->trade_status = OrderEnums::SpotTradeStatusProcessing;
            $order->volume = $payload->volume;
        } else {
            $order->price = $payload->marketPrice;
            $order->market_price = $payload->marketPrice;
            $order->match_price = $payload->matchPrice;
            $order->match_time = Carbon::now();
            $order->trade_status = OrderEnums::SpotTradeStatusDone;
            $order->volume = $payload->volume;
        }
        $order->save();
        return $order;
    }


    /**
     * 处理挂单
     * @param int $orderId 
     * @param mixed $latestPrice 
     * @return true 
     * @throws LogicException 
     * @throws DivisionByZeroError 
     * @throws InvalidArgumentException 
     * @throws InvalidCastException 
     */
    public function handelLimit(int $orderId, $latestPrice)
    {
        return DB::transaction(function () use ($orderId, $latestPrice) {

            $order = UserOrderSpot::where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                Log::error('现货处理限价单失败, 限价单状态不正确', ['id' => $orderId]);
                throw new LogicException('现货处理限价单失败, 没有找到订单数据');
            }
            if ($order->trade_status !== OrderEnums::SpotTradeStatusProcessing) {
                Log::error('现货处理限价单失败, 限价单状态不正确', ['id' => $orderId, 'status' => $order->trade_status]);
                throw new LogicException('现货处理限价单失败, 限价单状态不正确');
            }

            $order->market_price = $latestPrice;
            $spread = $order->spread;
            $spread = $order->side == OrderEnums::SideSell ? -$spread : $spread;
            $order->match_price = $spread ? bcsub($order->market_price, $spread, FundsEnums::DecimalPlaces) : $order->market_price;

            if ($order->side == OrderEnums::SideSell) {
                $order->trade_volume = bcmul($order->match_price , $order->volume, FundsEnums::DecimalPlaces);
            } else {
                $order->volume = bcdiv($order->trade_volume, $order->match_price, FundsEnums::DecimalPlaces);
            }

            $order->trade_status = OrderEnums::SpotTradeStatusDone;
            $order->match_time = Carbon::now();
            $order->save();

            $this->liquidationLockCoin($order);
            $this->liquidationTargeCoin($order);

            SendRefreshOrder::dispatch(User::find($order->uid));
            return true;
        });
    }

    /**
     * 清算锁定货币 - 挂单清算
     * @param UserOrderSpot $order 
     * @return true 
     * @throws BindingResolutionException 
     * @throws NotFoundExceptionInterface 
     * @throws ContainerExceptionInterface 
     * @throws LogicException 
     */
    public function liquidationLockCoin(UserOrderSpot $order)
    {
        $srcCoinId = CoinEnums::DefaultUSDTCoinID;
        $srcCoinQuantity = $order->trade_volume;

        if ($order->side == OrderEnums::SideSell) {
            $srcCoinId = $order->symbol->coin_id;
            $srcCoinQuantity = $order->volume;
        }

        $srcWallet = UserWalletSpot::where('uid', $order->uid)->where('coin_id', $srcCoinId)->lockForUpdate()->first();
        if (!$srcWallet) {
            throw new LogicException(__('Insufficient account balance'));
        }
        $d = bcsub($srcWallet->lock_amount, $srcCoinQuantity, FundsEnums::DecimalPlaces);
        if ($d < 0) {
            throw new LogicException('锁定金额不正确');
        }
        if ($order->trade_type == OrderEnums::SpotTradeStatusFailed) {
            $before = $srcWallet->amount;
            $srcWallet->amount = bcadd($srcWallet->amount, $srcCoinQuantity, FundsEnums::DecimalPlaces);

            $flow = new UserWalletSpotFlow();
            $flow->uid = $order->uid;
            $flow->coin_id = $srcCoinId;
            $flow->flow_type = SpotWalletFlowEnums::FlowTypeRefundPostingOrder;
            $flow->before_amount = $before;
            $flow->amount = -$srcCoinQuantity;
            $flow->after_amount = $srcWallet->amount;
            $flow->relation_id = $order->id;
            $flow->save();
        }

        $srcWallet->lock_amount = $d;
        $srcWallet->save();
        return true;
    }


    /**
     * 清算交易货币
     * @param UserOrderSpot $order 
     * @return true 
     * @throws BindingResolutionException 
     * @throws NotFoundExceptionInterface 
     * @throws ContainerExceptionInterface 
     * @throws LogicException 
     * @throws InvalidArgumentException 
     * @throws InvalidCastException 
     */
    public function liquidationSrcCoin(UserOrderSpot $order)
    {
        $srcCoinId = CoinEnums::DefaultUSDTCoinID;
        $srcCoinQuantity = $order->trade_volume;

        if ($order->side == OrderEnums::SideSell) {
            $srcCoinId = $order->symbol->coin_id;
            $srcCoinQuantity = $order->volume;
        }

        $srcWallet = UserWalletSpot::where('uid', $order->uid)->where('coin_id', $srcCoinId)->lockForUpdate()->first();
        if (!$srcWallet) {
            throw new LogicException(__('Insufficient account balance'));
        }
        $beforeSrcBalance = $srcWallet->amount;
        $d = bcsub($srcWallet->amount, $srcCoinQuantity, FundsEnums::DecimalPlaces);
        if ($d < 0) {
            throw new LogicException(__('Insufficient account balance'));
        }


        $flowType = '';
        if ($order->trade_type == OrderEnums::TradeTypeLimit) {
            $srcWallet->lock_amount = bcadd($srcWallet->lock_amount, $srcCoinQuantity, FundsEnums::DecimalPlaces);
            $flowType = SpotWalletFlowEnums::FlowTypePostingOrder;
        } else {
            $flowType = SpotWalletFlowEnums::FlowTypeExecution;
        }

        $srcWallet->amount = $d;
        $srcWallet->save();

        $flow = new UserWalletSpotFlow();
        $flow->uid = $order->uid;
        $flow->coin_id = $srcCoinId;
        $flow->flow_type = $flowType;
        $flow->before_amount = $beforeSrcBalance;
        $flow->amount = -$srcCoinQuantity;
        $flow->after_amount = $srcWallet->amount;
        $flow->relation_id = $order->id;
        $flow->save();
        return true;
    }

    /**
     * 清算报价货币
     * @param UserOrderSpot $order 
     * @return true 
     * @throws InvalidArgumentException 
     * @throws InvalidCastException 
     */
    public function liquidationTargeCoin(UserOrderSpot $order)
    {
        $targetCoinId = $order->symbol->coin_id;
        $targetCoinQuantity = $order->volume;

        if ($order->side == OrderEnums::SideSell) {
            $targetCoinId = CoinEnums::DefaultUSDTCoinID;
            $targetCoinQuantity = $order->trade_volume;
        }

        $targetWallet = UserWalletSpot::where('uid', $order->uid)->where('coin_id', $targetCoinId)->lockForUpdate()->first();
        if (!$targetWallet) {
            $targetWallet = new UserWalletSpot();
            $targetWallet->uid = $order->uid;
            $targetWallet->coin_id = $targetCoinId;
            $targetWallet->amount = 0;
            $targetWallet->save();
        }

        $beforeTargetBalance = $targetWallet->amount;
        $targetWallet->amount = bcadd($targetWallet->amount, $targetCoinQuantity, FundsEnums::DecimalPlaces);
        $targetWallet->save();

        $flow = new UserWalletSpotFlow();
        $flow->uid = $order->uid;
        $flow->coin_id = $targetCoinId;
        $flow->flow_type = SpotWalletFlowEnums::FlowTypeExecution;
        $flow->before_amount = $beforeTargetBalance;
        $flow->amount = $targetCoinQuantity;
        $flow->after_amount = $targetWallet->amount;
        $flow->relation_id = $order->id;
        $flow->save();
        return true;
    }
}
