<?php

namespace App\Internal\Order\Actions;

use App\Enums\CommonEnums;
use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Events\NewPledgeOrder as AuditPledgeOrderEvent;
use App\Exceptions\LogicException;
use App\Models\UserOrderPledge;
use App\Models\UserWalletFutures;
use App\Models\UserWalletPledgeFlow;
use App\Models\UserWalletSpot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class RollbackPledgeOrder
{

    public function __invoke($uid, $id)
    {
        return DB::transaction(function () use ($uid, $id) {
            $order = UserOrderPledge::find($id);
            if($order->status != OrderEnums::PledgeTradeStatusHold){
                return $this->fail(__('The order is not holding status.'));
            }

            $usdc = UserWalletSpot::where('uid', $order->uid)->where('coin_id', CommonEnums::USDCCoinID)->lockForUpdate()->first();
            if($usdc->amount < $order->quota){
                return $this->fail(__("There is not enough USDC balance in the user's wallet"));
            }
            $usdc->amount = bcsub($usdc->amount, $order->quota, FundsEnums::DecimalPlaces);
            $usdc->save();

            $coin = UserWalletSpot::where('uid', $order->uid)->where('coin_id', $order->coin_id)->lockForUpdate()->first();
            $coin->lock_amount  = bcsub($coin->lock_amount,  $order->amount, FundsEnums::DecimalPlaces);
            $coin->amount       = bcadd($coin->amount,  $order->amount, FundsEnums::DecimalPlaces);
            $coin->save();

            $order->delete();
            UserWalletPledgeFlow::where('relation_id', $id)->limit(1)->delete();

            Log::info('人工回撤质押订单', [
                'uid'       => $uid,
                'order_id'  => $id,
                'coinId'    => $order->coin_id,
                'amount'    => $order->amount,
                'quota'     => $order->quota
            ]);

            return true;
        });
    }

}
