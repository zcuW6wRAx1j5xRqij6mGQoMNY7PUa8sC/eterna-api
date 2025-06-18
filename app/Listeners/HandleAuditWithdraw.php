<?php

namespace App\Listeners;

use App\Enums\CoinEnums;
use App\Enums\FundsEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Events\AuditWithdraw;
use App\Exceptions\LogicException;
use App\Models\PlatformWallet;
use App\Models\UserSpotWalletCoin;
use App\Models\UserSpotWalletFlow;
use App\Models\UserWalletSpot;
use App\Models\UserWalletSpotFlow;
use App\Models\UserWithdraw;
use Illuminate\Contracts\Container\BindingResolutionException;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Internal\Pay\Services\UdunService;

use function Laravel\Prompts\error;

class HandleAuditWithdraw 
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
    public function handle(AuditWithdraw $event): void
    {
        $model = $event->userWithdraw;
        switch ($model->audit_status) {
            case FundsEnums::AuditStatusProcessRejected:
                $this->reject($model);
                break;
            case FundsEnums::AuditStatusProcessPassed:
                $this->accpet($model);
                break;
            default:
                Log:
                error('failed to handle withdraw audit : status incorrect', [
                    'withdraw' => $model,
                ]);
                break;
        }
        return;
    }

    /**
     * 同意
     * @param UserWithdraw $userWithdraw
     * @return true
     * @throws BindingResolutionException
     * @throws Exception
     */
    private function accpet(UserWithdraw $userWithdraw)
    {
        // 向u盾发起申请
        try {
            (new UdunService)->withdraw($userWithdraw, $userWithdraw->uid);
        } catch (\Throwable $e) {
            Log::error('发起u盾提现申请失败 : ' . $e->getMessage(), ['withdraw_id' => $userWithdraw->id, 'e' => $e]);
            $userWithdraw->audit_status = FundsEnums::AuditStatusProcessFailed;
            $userWithdraw->save();
            $this->refundMoney($userWithdraw);
        }
        return true;
    }

    /**
     * 拒绝
     * @param UserWithdraw $userWithdraw
     * @return mixed
     */
    private function reject(UserWithdraw $userWithdraw)
    {
        return $this->refundMoney($userWithdraw);
    }

    private function refundMoney(UserWithdraw $userWithdraw)
    {
        return DB::transaction(function () use ($userWithdraw) {
            $wallet = UserWalletSpot::where('uid', $userWithdraw->uid)->where('coin_id', $userWithdraw->coin_id)->first();
            if (!$wallet) {
                Log::error('failed to handle withdraw audit : no found user wallet', [
                    'withdraw' => $userWithdraw,
                    'uid' => $userWithdraw->uid,
                ]);
                throw new LogicException('failed to handle withdraw audit');
            }

            $beforeAmount = $wallet->amount;
            $d = bcsub($wallet->lock_amount, $userWithdraw->amount, FundsEnums::DecimalPlaces);
            if ($d < 0) {
                Log::error('failed to handle withdraw audit : user lock amount error', [
                    'withdraw' => $userWithdraw,
                    'withdraw_money' => $userWithdraw->amount,
                    'user_lock_amount' => $wallet->lock_amount,
                    'uid' => $userWithdraw->uid,
                ]);
                throw new LogicException('failed to handle withdraw audit');
            }
            $wallet->lock_amount = $d;
            $wallet->amount = bcadd($wallet->amount, $userWithdraw->amount, FundsEnums::DecimalPlaces);
            $wallet->save();

            // 返还手续费
            if ($userWithdraw->fee) {
                // 如果提现的是USDT /USDC , 就不用返还 , 上面返还过了
                if ($userWithdraw->coin_id != CoinEnums::DefaultUSDTCoinID) {
                    $usdc = UserWalletSpot::where('uid', $userWithdraw->uid)->where('coin_id', CoinEnums::DefaultUSDTCoinID)->first();
                    if ($usdc) {
                        $usdcBefore = $usdc->amount;
                        $usdc->amount = bcadd($usdc->amount, $userWithdraw->fee, FundsEnums::DecimalPlaces);
                        $usdc->save();
                        // 记录返还手续费
                        $usdcflow = new UserWalletSpotFlow();
                        $usdcflow->uid = $userWithdraw->uid;
                        $usdcflow->coin_id = CoinEnums::DefaultUSDTCoinID;
                        $usdcflow->flow_type = SpotWalletFlowEnums::FlowTypeWithdrawFeeRefund;
                        $usdcflow->before_amount = $usdcBefore;
                        $usdcflow->amount = $userWithdraw->fee;
                        $usdcflow->after_amount = $usdc->amount;
                        $usdcflow->relation_id = 0;
                        $usdcflow->save();
                    }
                }
            }


            $flow = new UserWalletSpotFlow();
            $flow->uid = $userWithdraw->uid;
            $flow->coin_id = $userWithdraw->coin_id;
            $flow->flow_type = SpotWalletFlowEnums::FlowTypeWithdrawRefund;
            $flow->before_amount = $beforeAmount;
            $flow->amount = $userWithdraw->amount;
            $flow->after_amount = $wallet->amount;
            $flow->relation_id = $userWithdraw->id;
            $flow->save();
            return true;
        });
    }
}
