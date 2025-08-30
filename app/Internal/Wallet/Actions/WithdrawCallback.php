<?php

namespace Internal\Wallet\Actions;

use App\Enums\FundsEnums;
use App\Enums\ThirdpartyEnums;
use App\Events\WithdrawReceived;
use App\Models\PlatformWallet;
use App\Models\UserWalletSpot;
use App\Models\UserWithdraw;
use Illuminate\Support\Facades\Log;

class WithdrawCallback {

    public function __invoke(array $data)
    {
        $address = $data['address'] ?? '';
        $amount = $data['amount'] ?? '';
        $businessId = $data['businessId'] ?? '';
        $coinType = $data['coinType'] ?? null;
        $decimals = $data['decimals'] ?? '';
        // $fee = $data['fee'] ?? 0;
        $mainCoinType = $data['mainCoinType'] ?? null;
        $status = $data['status'] ?? '';
        $tradeId = $data['tradeId'] ?? '';
        $txId = $data['txId'] ?? '';

        if ($status != ThirdpartyEnums::UdunCallbacKStatusTradeSuccess) {
            if ($status == ThirdpartyEnums::UdunCallbacKStatusAuditDone) {
                return true;
            }

            // 审核失败 , 退回款项
            if ($status == ThirdpartyEnums::UdunCallbacKStatusAuditReject) {
                Log::info('accpet UDun Reject withdraw',[
                    'data'=>$data,
                ]);
                Log::warning('failed to handle user deposit : UDun reject',[
                    'data'=>$data,
                ]);

                $withdraw = UserWithdraw::where('order_no', $businessId)->first();
                if (!$withdraw) {
                    Log::error('failed to handle user withdraw , data incorrect : no found withdraw record',[
                        'businessId'=>$businessId,
                        'data'=>$data,
                    ]);
                    return true;
                }
                $refundSrv = new RefundWithdraw();
                $refundSrv->rejectWithdrawByCallback($withdraw);
                $refundSrv->refundMoney($withdraw);
                return true;
            }

            Log::error('failed to handle user deposit , data incorrect : bad trade status',[
                'data'=>$data,
            ]);
            return true;
        }

        if (!$address) {
            Log::error('failed to handle user withdraw , data incorrect : no address',[
                'data'=>$data,
            ]);
            return true;
        }
        
        if ($mainCoinType === null || $coinType === null ) {
            Log::error('failed to handle user withdraw , data incorrect : no main cointype',[
                'data'=>$data,
            ]);
            return true;
        }

        $coin = PlatformWallet::where('udun_coin_type', $coinType)->where('udun_main_coin_type', $mainCoinType)->first();
        if (!$coin) {
            Log::error('failed to handle user withdraw , data incorrect : no found coin by mainCoinType',[
                'data'=>$data,
            ]);
            return true;
        }
        if (!$businessId) {
            Log::error('failed to handle user withdraw , data incorrect : no businessId',[
                'data'=>$data,
            ]);
            return true;
        }
        $withdraw = UserWithdraw::where('order_no', $businessId)->first();
        if (!$withdraw) {
            Log::error('failed to handle user withdraw , data incorrect : no found withdraw record',[
                'businessId'=>$businessId,
                'data'=>$data,
            ]);
            return true;
        }
        if ($withdraw->audit_status != FundsEnums::AuditStatusProcessPassed && $withdraw->status != FundsEnums::WithdrawStatusProcessing) {
            Log::error('failed to handle user withdraw , data incorrect : bad withdraw status',[
                'withdraw_status'=>$withdraw->status,
                'withdraw_audit_status'=>$withdraw->audit_status,
                'businessId'=>$businessId,
                'data'=>$data,
            ]);
            return true;
        }

        $amount = abs($amount);
        $decimals = abs(intval($decimals));

        if (!$amount || !$decimals) {
            Log::error('failed to handle user withdraw , data incorrect : no amount or no decimals',[
                'data'=>$data,
            ]);
            return true;
        }

        $realAmount = bcdiv($amount, bcpow(10, $decimals), FundsEnums::DecimalPlaces);
        if ($realAmount <= 0 ) {
            Log::error('failed to handle user withdraw, data incorrect : bad realAmount',[
                'data'=>$data,
            ]);
            return true;
        }

        $withdraw->udun_logic_id = $tradeId;
        $withdraw->udun_block_id = $txId;
        $withdraw->unique_callback = $address .'!'. ThirdpartyEnums::UdunCallbackTypeDeposit.'!'.$txId.'!'.$status;
        $withdraw->status = FundsEnums::WithdrawStatusDone;
        // $withdraw->real_amount = $realAmount;
        $withdraw->callback_raw = $data;
        $withdraw->save();

        // 解除锁定金额
        $wallet = UserWalletSpot::where('uid', $withdraw->uid)->where('coin_id', $withdraw->coin_id)->first();
        if (!$wallet) {
            Log::error('failed to handle withdraw audit : no found user wallet',[
                'withdraw'=> $withdraw,
                'uid'=>$withdraw->uid,
            ]);
            return false;
        }
        $wallet->lock_amount = bcsub($wallet->lock_amount, $withdraw->amount, FundsEnums::DecimalPlaces);
        $wallet->save();

        WithdrawReceived::dispatch($withdraw);
        return true;
    }
}

