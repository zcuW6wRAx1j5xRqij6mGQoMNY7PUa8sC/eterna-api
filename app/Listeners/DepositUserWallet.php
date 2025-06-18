<?php

namespace App\Listeners;

use App\Enums\CoinEnums;
use App\Enums\FundsEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Events\NewDeposit;
use App\Models\UserSpotWalletCoin;
use App\Models\UserSpotWalletFlow;
use App\Models\UserWallet;
use App\Models\UserWalletAddress;
use App\Models\UserWalletSpot;
use App\Models\UserWalletSpotFlow;
use Illuminate\Console\View\Components\Confirm;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepositUserWallet implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(NewDeposit $event): void
    {
        DB::transaction(function() use($event){
            $deposit = $event->userDeposit;
            // 2024/11/7 入金全部处理成 usdt
            // 加钱
            $wallet =  UserWalletSpot::where('uid', $deposit->uid)->where('coin_id', CoinEnums::DefaultUSDTCoinID)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = new UserWalletSpot();
                $wallet->uid = $deposit->uid;
                $wallet->coin_id = CoinEnums::DefaultUSDTCoinID;
                $wallet->amount = 0;
                $wallet->lock_amount = 0;
                $wallet->usdt_value = 0;
                $wallet->save();
            }

            $before = $wallet->amount;
            $depositAmount = $deposit->usdt_value; // 实际入金为usdt金额
            $wallet->amount = bcadd($wallet->amount,$depositAmount , FundsEnums::DecimalPlaces);
            $wallet->save();

            $flow = new UserWalletSpotFlow();
            $flow->uid = $deposit->uid;
            $flow->coin_id = CoinEnums::DefaultUSDTCoinID;
            $flow->flow_type = SpotWalletFlowEnums::FlowTypeDeposit;
            $flow->before_amount = $before;
            $flow->amount = $depositAmount;
            $flow->after_amount =$wallet->amount;
            $flow->relation_id = $deposit->id;
            $flow->save();

            $userAddress = UserWalletAddress::where('uid', $deposit->uid)->where('address',$deposit->wallet_address)->first();
            if (!$userAddress) {
                Log::error('failed to deposit user wallet : no found user total wallet model',[
                    'deposit'=>$event->userDeposit->toArray(),
                    'uid'=>$deposit->uid,
                ]);
                return true;
            }
            // 统计总值
            $userAddress->total_deposit = bcadd($userAddress->total_deposit, $depositAmount, FundsEnums::DecimalPlaces);
            $userAddress->total_deposit_usdt = bcadd($userAddress->total_deposit_usdt, $deposit->usdt_value, FundsEnums::DecimalPlaces);
            $userAddress->save();

            return true;
        });
        return;
    }
}
