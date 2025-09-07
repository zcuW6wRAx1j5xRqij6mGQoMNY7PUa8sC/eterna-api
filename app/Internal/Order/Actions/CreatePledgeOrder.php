<?php

namespace App\Internal\Order\Actions;

use App\Enums\CommonEnums;
use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Events\NewPledgeOrder;
use App\Exceptions\LogicException;
use App\Internal\Order\Payloads\PledgeOrderPayload;
use App\Models\PledgeCoinConfig;
use App\Models\UserOrderPledge;
use App\Models\UserWalletPledgeFlow;
use App\Models\UserWalletSpot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CreatePledgeOrder
{

    public function __invoke(PledgeOrderPayload $payload)
    {
        if (!UserWalletSpot::where('uid', $payload->user->id)->where('coin_id', $payload->coinId)->exists()) {
            $wallet             = new UserWalletSpot();
            $wallet->uid        = $payload->user->id;
            $wallet->coin_id    = $payload->coinId;
            $wallet->save();
        }

        $unexpect = [
            OrderEnums::PledgeTradeStatusPending,
            OrderEnums::PledgeTradeStatusHold,
            OrderEnums::PledgeTradeStatusClosing,
            OrderEnums::PledgeTradeStatusRejected,
        ];
        $onProcessing = UserOrderPledge::where('uid', $payload->user->id)
            ->where('status', '<>', OrderEnums::PledgeTradeStatusRejected)
            ->exists();
        if($onProcessing){
            throw new LogicException(__('OnNur ein Auftrag kann gleichzeitig bearbeitet werden.'));
        }

        return DB::transaction(function () use ($payload) {
            $uid    = $payload->user->id;
            $amount = $payload->amount;
            $coinId = $payload->coinId;

            // 查看资金是否足够
            $wallet = UserWalletSpot::where('uid', $uid)
                ->where('coin_id', $coinId)
                ->lockForUpdate()
                ->first();
            if (!$wallet) {
                // 不存在
                Log::error('failed to create pledge order : no found user wallet', [
                    'uid' => $payload->user->id,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }

            // 扣除
            $d = bcsub($wallet->amount, $amount, FundsEnums::DecimalPlaces);
            if ($d < 0) {
                throw new LogicException(__('Insufficient account balance'));// . "({$wallet->id} - {$wallet->amount} - {$amount})"));
            }
            $before                 = $wallet->amount;
            $wallet->amount         = $d;
            $wallet->lock_amount    = bcadd($wallet->lock_amount, $amount, FundsEnums::DecimalPlaces);
            $wallet->save();

            // 创建订单
            $order = $this->makeOrder($payload);

            // 增加流水信息
            $flow = new UserWalletPledgeFlow();
            $flow->uid              = $uid;
            $flow->coin_id          = $coinId;
            $flow->flow_type        = OrderEnums::PledgeTradeStatusPending;
            $flow->before_amount    = $before;
            $flow->amount           = -$amount;
            $flow->after_amount     = $wallet->amount;
            $flow->relation_id      = $order->id;
            $flow->save();

            NewPledgeOrder::dispatch($order);
            return true;
        });
    }

    public function makeOrder(PledgeOrderPayload $payload)
    {
        $order                      = new UserOrderPledge();
        $order->uid                 = $payload->user->id;
        $order->coin_id             = $payload->coinId;
        $order->amount              = $payload->amount;
        $order->market_price        = $payload->marketPrice;
        $order->quota               = $payload->USDCNum;
        $order->duration            = $payload->duration;
        $order->principal_remain    = $payload->amount;//剩余赎回本金
        $order->redeem_remain       = $payload->USDCNum;//剩余回退usdc
        $order->status              = OrderEnums::FuturesTradeStatusProcessing;
        $order->save();
        return $order;
    }

    /**
     * 是否准许质押的货币类型
     * @param int $coinId
     * @return bool
     * @throws InvalidArgumentException
     */
    public function allowPledgeCoin(int $coinId)
    {
        return PledgeCoinConfig::query()
            ->where('coin_id', $coinId)
            ->where('status', CommonEnums::Yes)
            ->exists();
    }
}
