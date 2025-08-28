<?php

namespace App\Internal\Order\Actions;

use App\Enums\CoinEnums;
use App\Enums\CommonEnums;
use App\Enums\ConfigEnums;
use App\Enums\FundsEnums;
use App\Enums\SpotWalletFlowEnums;
use App\Exceptions\LogicException;
use App\Models\UserWalletSpot;
use App\Models\UserWalletSpotFlow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Internal\Common\Services\ConfigService;
use Internal\Market\Actions\FetchSymbolQuote;
use Internal\Wallet\Actions\UpdateSpotWalletUsdt;

/** @package Internal\Order\Actions */
class CreateInstantExchangeOrder
{

    public function __invoke($fromCoinID, $quantity, $toCoinID, $userID)
    {
        if ($quantity <= 0) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }

        return DB::transaction(function () use ($fromCoinID, $toCoinID, $quantity, $userID) {
            // 源货币钱包
            $originCoinWallet = UserWalletSpot::query()->with(['coin'])
                ->where('uid', $userID)
                ->lockForUpdate()
                ->where('coin_id', $fromCoinID)
                ->first();
            if (!$originCoinWallet) {
                $wallet = UserWalletSpot::create([
                    'uid'     => $userID,
                    'coin_id' => $fromCoinID,
                ]);
                Log::error('failed to create instant spot order : no wallet', [
                    'uid'     => $userID,
                    'coin_id' => $fromCoinID,
                    'wallet'  => $wallet->id,
                ]);
                throw new LogicException(__('insufficient balance'));
            }

            if ($originCoinWallet->amount < $quantity) {
                throw new LogicException(__('insufficient balance'));
            }

            //目标货币钱包
            $targetCoinWallet = UserWalletSpot::query()->with(['coin'])
                ->where('uid', $userID)
                ->where('coin_id', $toCoinID)
                ->lockForUpdate()
                ->first();
            if (!$targetCoinWallet) {
                $row = UserWalletSpot::create([
                    'uid'     => $userID,
                    'coin_id' => $toCoinID,
                ]);

                $targetCoinWallet = UserWalletSpot::query()->with(['coin'])->find($row->id);
                if (!$targetCoinWallet) {
                    Log::error('failed to create instant spot order : build user wallet fail', [
                        'uid'     => $userID,
                        'coin_id' => $toCoinID,
                    ]);
                    throw new LogicException(__('Whoops! Something went wrong'));
                }
            }

            $originCoinMarketPrice = 1;
            $targetCoinMarketPrice = 1;

            // 获取源币种最新市价
            if($fromCoinID != CoinEnums::DefaultUSDTCoinID){
                $bs = strtolower($originCoinWallet->coin->name.'usdt');
                $originCoinMarketPrice = (new FetchSymbolQuote)($bs);
                if (!$originCoinMarketPrice) {
                    $bs = strtolower($originCoinWallet->coin->name.'usdc');
                    $originCoinMarketPrice = (new FetchSymbolQuote)($bs);
                }
                if (!$originCoinMarketPrice) {
                    Log::error('failed to create instant spot order : no origin coin market price', [
                        'symbolId' => $originCoinWallet->coin_id,
                        'coinName' => $originCoinWallet->coin->name,
                        'bs'       => $bs,
                    ]);
                    throw new LogicException(__('Whoops! Something went wrong'));
                }
            }
            // 获取目标币种最新市价
            if($toCoinID != CoinEnums::DefaultUSDTCoinID){
                $bs = $targetCoinWallet->coin->name.'usdt';
                $targetCoinMarketPrice = (new FetchSymbolQuote)($bs);
                if (!$targetCoinMarketPrice) {
                    $bs = $targetCoinWallet->coin->name.'usdc';
                    $targetCoinMarketPrice = (new FetchSymbolQuote)($bs);
                }
                if (!$targetCoinMarketPrice) {
                    Log::error('failed to create spot order : no target coin market price', [
                        'symbolId' => $targetCoinWallet->coin_id,
                        'coinName' => $targetCoinWallet->coin->name,
                        'bs'       => $bs,
                    ]);
                    throw new LogicException(__('Whoops! Something went wrong'));
                }
            }

            $fee             = ConfigService::getIns()->fetch(ConfigEnums::PlatformConfigInstantExchangeFee, 0.00);//手续费比率
            $USDTAmount      = bcdiv($quantity, $originCoinMarketPrice, FundsEnums::DecimalPlaces);//源币->USDT
            $newTargetAmount = bcdiv($USDTAmount, $targetCoinMarketPrice, FundsEnums::DecimalPlaces);//USDT->目标币
            $fee             = bcmul($newTargetAmount, $fee, FundsEnums::DecimalPlaces);
            $newTargetAmount = bcsub($newTargetAmount, $fee, FundsEnums::DecimalPlaces); //扣除手续费
            if ($newTargetAmount < 0) {
                throw new LogicException(__('insufficient balance'));
            }

            $before                       = $originCoinWallet->amount;
            $originCoinWallet->amount     = bcsub($originCoinWallet->amount, $quantity, FundsEnums::DecimalPlaces);
            $originCoinWallet->usdt_value = bcdiv($originCoinWallet->amount, $originCoinMarketPrice, FundsEnums::DecimalPlaces);
            $originCoinWallet->save();

            $extra = ['target' => $toCoinID, 'quantity' => $quantity, 'market_price' => $originCoinMarketPrice];

            $flow                = new UserWalletSpotFlow();
            $flow->uid           = $userID;
            $flow->coin_id       = $fromCoinID;
            $flow->flow_type     = SpotWalletFlowEnums::FlowTypeInstantExchangeDeduct;
            $flow->before_amount = $before;
            $flow->amount        = -$quantity;
            $flow->after_amount  = $originCoinWallet->amount;
            $flow->extra         = json_encode($extra);
            $flow->save();

            $before                       = $targetCoinWallet->amount;
            $targetCoinWallet->amount     = bcadd($targetCoinWallet->amount, $newTargetAmount, FundsEnums::DecimalPlaces);
            $targetCoinWallet->usdt_value = bcdiv($targetCoinWallet->amount, $targetCoinMarketPrice, FundsEnums::DecimalPlaces);
            $targetCoinWallet->save();

            $flow2                = new UserWalletSpotFlow();
            $flow2->uid           = $userID;
            $flow2->coin_id       = $toCoinID;
            $flow2->flow_type     = SpotWalletFlowEnums::FlowTypeInstantExchangeAdd;
            $flow2->before_amount = $before;
            $flow2->amount        = $newTargetAmount;
            $flow2->after_amount  = $targetCoinWallet->amount;
            $flow2->relation_id   = $flow->id;
            $flow2->extra         = json_encode(array_merge($extra, ['market_price' => $targetCoinMarketPrice, 'fee' => $fee]));
            $flow2->save();

            $flow->relation_id = $flow2->id;
            $flow->save();
        });
    }

}
