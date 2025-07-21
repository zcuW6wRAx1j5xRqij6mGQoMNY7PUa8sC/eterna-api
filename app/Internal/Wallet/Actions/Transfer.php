<?php

namespace Internal\Wallet\Actions;

use App\Enums\CoinEnums;
use App\Enums\FundsEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Enums\TransferEnums;
use App\Enums\WalletFuturesFlowEnums;
use App\Events\UserFuturesBalanceUpdate;
use App\Exceptions\LogicException;
use App\Models\User;
use App\Models\UserWalletFutures;
use App\Models\UserWalletFuturesFlow;
use App\Models\UserWalletSpot;
use App\Models\UserWalletSpotFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Internal\Order\Actions\FetchAvaiableChangeMoney;

class Transfer {

    public function __invoke(Request $request, User $user)
    {
        return DB::transaction(function() use($request, $user){
            $toWallet = $request->get('to');
            $amount = $request->get('amount',0);
            if (!is_numeric($amount)) {
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            $amount = abs($amount);
            if ($amount <= 0) {
                throw new LogicException(__('The amount is incorrect'));
            }

            $spotWallet = UserWalletSpot::where('uid', $user->id)->where('coin_id',CoinEnums::DefaultUSDTCoinID)->lockForUpdate()->first();
            if (!$spotWallet) {
                throw new LogicException(__('Insufficient account balance'));
            }
            $derivativeWallet = UserWalletFutures::where('uid', $user->id)->first();
            if (!$derivativeWallet) {
                $derivativeWallet = new UserWalletFutures();
                $derivativeWallet->uid = $user->id;
                $derivativeWallet->save();
                $derivativeWallet = UserWalletFutures::where('uid', $user->id)->first();
            }

            switch($toWallet) {
                case TransferEnums::WalletSpot:
                    $this->toSpotWallet($user, $derivativeWallet, $spotWallet, $amount);
                break;
                case TransferEnums::WalletDerivative:
                    $this->toDerivativeWallet($user, $derivativeWallet, $spotWallet, $amount);
                break;
                default:
                    throw new LogicException(__('Whoops! Something went wrong'));
            }
            return true;
        });
    }

    public function toSpotWallet(User $user,UserWalletFutures $userDerivativelWallet,UserWalletSpot $userSpotWalletCoin, $amount) {
        $allowMoney = (new FetchAvaiableChangeMoney)($user);
        if (bcsub($allowMoney, $amount, FundsEnums::DecimalPlaces) < 0) {
            throw new LogicException(__('Insufficient account balance'));
        }


        $beforeFrom = $userDerivativelWallet->balance;
        $d = bcsub($userDerivativelWallet->balance , $amount, FundsEnums::DecimalPlaces);
        if ($d < 0) {
            throw new LogicException(__('Insufficient account balance'));
        }
        $userDerivativelWallet->balance = $d;
        $userDerivativelWallet->save();

        $flow = new UserWalletFuturesFlow();
        $flow->uid = $user->id;
        $flow->flow_type = WalletFuturesFlowEnums::FlowTransferOut;
        $flow->before_amount = $beforeFrom;
        $flow->amount = $amount;
        $flow->after_amount = $userDerivativelWallet->balance;
        $flow->save();


        $before =$userSpotWalletCoin->amount;
        $userSpotWalletCoin->amount = bcadd($userSpotWalletCoin->amount , $amount, FundsEnums::DecimalPlaces);
        $userSpotWalletCoin->save();

        $flow = new UserWalletSpotFlow();
        $flow->uid =$user->id;
        $flow->coin_id = $userSpotWalletCoin->coin_id;
        $flow->flow_type = SpotWalletFlowEnums::FlowTypeTransferIn;
        $flow->before_amount = $before;
        $flow->amount = $amount;
        $flow->after_amount = $userSpotWalletCoin->amount;
        $flow->save();

        UserFuturesBalanceUpdate::dispatch($user->id,floatTransferString($userDerivativelWallet->balance));
        return true;
    }

    public function toDerivativeWallet(User $user, UserWalletFutures $userDerivativelWallet, UserWalletSpot $userSpotWalletCoin, $amount) {
        $beforeFrom = $userSpotWalletCoin->amount;
        $d = bcsub($userSpotWalletCoin->amount, $amount, FundsEnums::DecimalPlaces);
        if ($d < 0) {
            throw new LogicException(__('Insufficient account balance'));
        }
        $userSpotWalletCoin->amount = $d;
        $userSpotWalletCoin->save();

        $flow = new UserWalletSpotFlow();
        $flow->uid =$user->id;
        $flow->coin_id = $userSpotWalletCoin->coin_id;
        $flow->flow_type = SpotWalletFlowEnums::FlowTypeTransferOut;
        $flow->before_amount = $beforeFrom;
        $flow->amount = $amount;
        $flow->after_amount = $userSpotWalletCoin->amount;
        $flow->save();


        $before = $userDerivativelWallet->balance;
        $userDerivativelWallet->balance = bcadd($userDerivativelWallet->balance , $amount, FundsEnums::DecimalPlaces);
        $userDerivativelWallet->save();

        $flow = new UserWalletFuturesFlow();
        $flow->uid = $user->id;
        $flow->flow_type = WalletFuturesFlowEnums::FlowTransferIn;
        $flow->before_amount = $before;
        $flow->amount = $amount;
        $flow->after_amount = $userDerivativelWallet->balance;
        $flow->save();

        UserFuturesBalanceUpdate::dispatch($user->id,floatTransferString($userDerivativelWallet->balance));
        return true;
    }

}

