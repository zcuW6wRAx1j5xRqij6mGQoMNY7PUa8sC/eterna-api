<?php

namespace Internal\Wallet\Actions;

use App\Enums\CoinEnums;
use App\Enums\CommonEnums;
use App\Enums\ConfigEnums;
use App\Enums\FundsEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Events\SubmitUserWithdraw;
use App\Exceptions\LogicException;
use App\Models\PlatformActiveSupport;
use App\Models\PlatformWallet;
use App\Models\UserWalletSpot;
use App\Models\UserWalletSpotFlow;
use App\Models\UserWithdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Internal\Common\Services\ConfigService;

class SubmitWithdraw
{

    public function __invoke(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $walletId = $request->get('coin_id');
            $amount = $request->get('amount', 0);
            $blockName = $request->get('child_name');
            $amount = parseNumber($amount);
            $walletAddr = $request->get('wallet_address');

            if ($amount <= 0) {
                throw new LogicException(__('Amount is invalid'));
            }
            
            $platform = PlatformWallet::find($walletId);
            if (!$platform) {
                Log::error('failed to submit withdraw : no found platform wallet', [
                    'all' => $request->all(),
                    'uid' => $request->user()->id,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            if ($platform->spot_withdraw != CommonEnums::Yes) {
                throw new LogicException(__('Withdrawal of the current Coin is temporarily suspended.'));
            }

            $wallet = UserWalletSpot::where('uid', $request->user()->id)->where('coin_id', $platform->coin_id)->lockForUpdate()->first();
            if (!$wallet) {
                throw new LogicException(__('Insufficient account balance'));
            }

            // 提现手续费
            $fee = ConfigService::getIns()->fetch(ConfigEnums::PlatformConfigWithdrawFee, 0);
            if ($fee) {
                // 如果提现的是USDT/USDC , 直接扣除
                if ($platform->coin_id == CoinEnums::DefaultUSDTCoinID) {
                    $amount = bcadd($amount, $fee, FundsEnums::DecimalPlaces);
                } else {
                    // 检查USDC 是否满足手续费扣件
                    $usdc = UserWalletSpot::where('uid', $request->user()->id)->where('coin_id', CoinEnums::DefaultUSDTCoinID)->lockForUpdate()->first();
                    if (!$usdc) {
                        throw new LogicException(__('Insufficient available balance, unable to pay the service fee'));
                    }
                    $usdcBefore = $usdc->amount;
                    $feeD = bcsub($usdc->amount, $fee, FundsEnums::DecimalPlaces);
                    if ($feeD < 0) {
                        throw new LogicException(__('Insufficient available balance, unable to pay the service fee'));
                    }
                    $usdc->amount = $feeD;
                    $usdc->save();
                    $usdcflow = new UserWalletSpotFlow();
                    $usdcflow->uid = $request->user()->id;
                    $usdcflow->coin_id = $platform->coin_id;
                    $usdcflow->flow_type = SpotWalletFlowEnums::FlowTypeWithdrawFee;
                    $usdcflow->before_amount = $usdcBefore;
                    $usdcflow->amount = $amount;
                    $usdcflow->after_amount = $wallet->amount;
                    $usdcflow->relation_id = 0;
                    $usdcflow->save();
                }
            }

            $before = $wallet->amount;
            $d = bcsub($wallet->amount, $amount, FundsEnums::DecimalPlaces);
            $afterAmount = bcsub($wallet->amount, $amount, FundsEnums::DecimalPlaces);

            if ($d < 0) {
                throw new LogicException(__('Insufficient account balance'));
            }

            $wallet->lock_amount = bcadd($wallet->lock_amount, $amount, FundsEnums::DecimalPlaces);
            $wallet->amount = $afterAmount;
            $wallet->save();

            $withdraw = new UserWithdraw();
            $withdraw->order_no = generateUuid();
            $withdraw->coin_id = $platform->coin_id;
            $withdraw->wallet_id = $platform->id;
            $withdraw->block = $blockName;
            $withdraw->fee = $fee;
            $withdraw->uid = $request->user()->id;
            $withdraw->receive_wallet_address = $walletAddr;
            $withdraw->amount = $amount;
            $withdraw->real_amount = $amount;
            $withdraw->save();

            $flow = new UserWalletSpotFlow();
            $flow->uid = $request->user()->id;
            $flow->coin_id = $platform->coin_id;
            $flow->flow_type = SpotWalletFlowEnums::FlowTypeWithdraw;
            $flow->before_amount = $before;
            $flow->amount = $amount;
            $flow->after_amount = $wallet->amount;
            $flow->relation_id = $withdraw->id;
            $flow->save();

            SubmitUserWithdraw::dispatch($withdraw);
            return true;
        });
    }
}
