<?php

namespace Internal\Wallet\Actions;

use App\Enums\CoinEnums;
use App\Enums\FundsEnums;
use App\Enums\ThirdpartyEnums;
use App\Events\NewDeposit;
use App\Models\PlatformWallet;
use App\Models\UserDeposit;
use App\Models\UserWallet;
use App\Models\UserWalletAddress;
use App\Models\UserWalletSpot;
use Illuminate\Support\Facades\Log;
use Internal\Market\Services\BinanceService;

class DepositCallback {

    public function __invoke(array $data)
    {
        $address = $data['address'] ?? '';
        $amount = $data['amount'] ?? '';
        $coinType = $data['coinType'] ?? null;
        $decimals = $data['decimals'] ?? '';
        $fee = $data['fee'] ?? 0;
        $mainCoinType = $data['mainCoinType'] ?? null;
        $status = $data['status'] ?? '';
        $tradeId = $data['tradeId'] ?? '';
        $txId = $data['txId'] ?? '';

        if (!$address) {
            Log::error('failed to handle user deposit , data incorrect : no address',[
                'data'=>$data,
            ]);
            return true;
        }
        if ($mainCoinType === null || $coinType === null ) {
            Log::error('failed to handle user deposit , data incorrect : no main cointype',[
                'data'=>$data,
            ]);
            return true;
        }

        $coin = PlatformWallet::where('udun_coin_type', $coinType)->where('udun_main_coin_type', $mainCoinType)->first();
        if (!$coin) {
            Log::error('failed to handle user deposit , data incorrect : no found coin by mainCoinType',[
                'data'=>$data,
            ]);
            return true;
        }

        $wallet = UserWalletAddress::where('platform_wallet_id', $coin->id)->where('address', $address)->first();
        if (!$wallet) {
            Log::error('failed to handle user deposit , data incorrect : no found user wallet by address',[
                'data'=>$data,
            ]);
            return true;
        }
        // todo 文档说 address+tradetype+txid+status 校验唯一性 , 不确定
        $depositExists = UserDeposit::where('uid', $wallet->uid)->where('udun_block_id', $txId)->first();
        if ($depositExists) {
            Log::error('failed to handle user deposit , data incorrect : multiple request',[
                'data'=>$data,
            ]);
            return true;
        }

        $decimals = abs(intval($decimals));

        if (!$amount || !$decimals) {
            Log::error('failed to handle user deposit , data incorrect : no amount or no decimals',[
                'data'=>$data,
            ]);
            return true;
        }


        $realAmount = bcdiv($amount, bcpow(10, $decimals), FundsEnums::DecimalPlaces);
        if ($realAmount <= 0 ) {
            Log::error('failed to handle user deposit , data incorrect : bad realAmount',[
                'data'=>$data,
            ]);
            return true;
        }


        if ($status !== ThirdpartyEnums::UdunCallbacKStatusTradeSuccess) {
            Log::error('failed to handle user deposit , data incorrect : bad trade status',[
                    'data'=>$data,
            ]);
            return true;
        }

        $usdtValue = $realAmount;
        if (strtolower($coin->name) != 'usdt') {
            // 获取最新市价
            $marketPrice = BinanceService::getInstance()->fetchSymbolSpotQuote($coin->binance_symbol);
            if (!$marketPrice) {
                Log::error('failed to handle user deposit : failed to transfer usdt value, no market price',[
                        'data'=>$data,
                ]);
                $usdtValue = 0;
                // return true;
            } else {
                $usdtValue = bcmul($marketPrice, $realAmount,FundsEnums::DecimalPlaces);
            }
        }

        $deposit = new UserDeposit();
        $deposit->uid = $wallet->uid;
        $deposit->coin_id = $coin->coin_id;
        $deposit->udun_logic_id = $tradeId;
        $deposit->udun_block_id = $txId;
        $deposit->unique_callback = $address .'!'. ThirdpartyEnums::UdunCallbackTypeDeposit.'!'.$txId.'!'.$status;
        $deposit->wallet_address = $address;
        $deposit->amount = $realAmount;
        $deposit->usdt_value = $usdtValue;
        $deposit->real_amount = $realAmount;
        $deposit->fee = $fee;
        $deposit->callback_raw = $data;
        $deposit->status = FundsEnums::AuditStatusProcessPassed;
        $deposit->save();

        NewDeposit::dispatch($deposit);
        return true;
    }
}

