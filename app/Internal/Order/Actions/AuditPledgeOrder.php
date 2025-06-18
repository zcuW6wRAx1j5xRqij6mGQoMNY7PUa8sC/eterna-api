<?php

namespace App\Internal\Order\Actions;

use App\Enums\CommonEnums;
use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Events\NewPledgeOrder as AuditPledgeOrderEvent;
use App\Exceptions\LogicException;
use App\Models\UserOrderPledge;
use App\Models\UserWalletPledgeFlow;
use App\Models\UserWalletSpot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AuditPledgeOrder
{

    public function __invoke(Request $request)
    {
        $id     = $request->get('id');
        $order  = UserOrderPledge::find($id);
        if(!$order || $order->status != OrderEnums::PledgeTradeStatusPending){
            throw new InvalidArgumentException(__('Order not found'));
        }
        if (!UserWalletSpot::where('uid', $order->uid)->where('coin_id', CommonEnums::USDCCoinID)->exists()) {
            $wallet             = new UserWalletSpot();
            $wallet->uid        = $order->uid;
            $wallet->coin_id    = CommonEnums::USDCCoinID;
            $wallet->save();
        }

        $unexpect = [
            OrderEnums::PledgeTradeStatusHold,
            OrderEnums::PledgeTradeStatusClosing,
        ];
        $onProcessing = UserOrderPledge::where('uid', $order->uid)->whereIn('status', $unexpect)->exists();
        if($onProcessing){
            throw new LogicException(__('Only one order can be held simultaneously'));
        }

        return DB::transaction(function () use ($request, $order) {
            $operator       = $request->user()->id;
            $status         = $request->get('status');

            $uid = $order->uid;
            $coinId = $order->coin_id;
            $amount = $order->amount;

            $wallet = UserWalletSpot::where('uid', $uid)
                ->where('coin_id', $coinId)
                ->lockForUpdate()
                ->first();
            if (!$wallet) {
                // 不存在
                Log::error('failed to create pledge order : no found user wallet', [
                    'uid' => $uid,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }

            if($status == CommonEnums::No){
                // 拒审
                $d = bcadd($wallet->amount, $amount, FundsEnums::DecimalPlaces);
                if ($d < 0) {
                    throw new LogicException(__('Insufficient account balance'));
                }

                $order->start_at    = carbon::now()->toDateTimeString();
                $order->end_at      = carbon::now()->addDays($order->duration)->toDateString();
                $order->status      = OrderEnums::PledgeTradeStatusRejected;
                $order->operator    = $operator;
                $order->save();

                $before                 = $wallet->amount;
                $wallet->amount         = $d;
                $wallet->lock_amount    = bcsub($wallet->lock_amount, $amount, FundsEnums::DecimalPlaces);
                $wallet->save();


                // 增加流水信息
                $flow = new UserWalletPledgeFlow();
                $flow->uid              = $uid;
                $flow->coin_id          = $coinId;
                $flow->flow_type        = OrderEnums::PledgeTradeStatusRejected;
                $flow->before_amount    = $before;
                $flow->amount           = $amount;
                $flow->after_amount     = $wallet->amount;
                $flow->relation_id      = $order->id;
                $flow->save();

                AuditPledgeOrderEvent::dispatch($order);
                return true;
            }

            // 通过
            $wallet = UserWalletSpot::where('uid', $uid)
                ->where('coin_id', CommonEnums::USDCCoinID)
                ->lockForUpdate()
                ->first();
            if (!$wallet) {
                // 不存在
                Log::error('failed to create pledge order : no found user wallet', [
                    'uid' => $uid,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }

            $d = bcadd($wallet->amount, $order->quota, FundsEnums::DecimalPlaces);
            if ($d < 0) {
                throw new LogicException(__('Insufficient account balance'));
            }

            // +usdc
            $order->start_at    = carbon::now()->toDateTimeString();
            $order->end_at      = carbon::now()->addDays($order->duration)->toDateString();
            $order->status      = OrderEnums::PledgeTradeStatusHold;
            $order->operator    = $operator;
            $order->save();

            $before                 = $wallet->amount;
            $wallet->amount         = $d;
            $wallet->save();


            // 增加流水信息
            $flow = new UserWalletPledgeFlow();
            $flow->uid              = $uid;
            $flow->coin_id          = CommonEnums::USDCCoinID;//$coinId;
            $flow->flow_type        = OrderEnums::PledgeTradeStatusHold;
            $flow->before_amount    = $before;
            $flow->amount           = $order->quota;
            $flow->after_amount     = $wallet->amount;
            $flow->relation_id      = $order->id;
            $flow->save();

            AuditPledgeOrderEvent::dispatch($order);
            return true;
        });
    }

}
