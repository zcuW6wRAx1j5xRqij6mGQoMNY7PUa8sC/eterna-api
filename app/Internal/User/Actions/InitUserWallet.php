<?php

namespace Internal\User\Actions;

use App\Enums\CoinEnums;
use App\Models\PlatformWallet;
use App\Models\User;
use App\Models\UserWalletAddress;
use App\Models\UserWalletFutures;
use App\Models\UserWalletSpot;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Internal\Pay\Services\UdunService;

class InitUserWallet
{
    public function __invoke(User $user)
    {
        return DB::transaction(function () use ($user) {
            // 用户资产钱包
            $exists = UserWalletAddress::where('uid', $user->id)->first();
            if (!$exists) {
                if (App::environment('prod')) {
                    $this->createUdunWallet($user);
                } else {
                    $this->createTestWalletAddr($user);;
                }
            }

            // 合约钱包
            $exists = UserWalletFutures::where('uid', $user->id)->first();
            if (!$exists) {
                $futures = new UserWalletFutures();
                $futures->uid = $user->id;
                $futures->save();
            }

            // 合约钱包 : usdt
            $exists = UserWalletSpot::where('uid', $user->id)->where('coin_id', CoinEnums::DefaultUSDTCoinID)->first();
            if (!$exists) {
                $spotWallet = new UserWalletSpot();
                $spotWallet->uid = $user->id;
                $spotWallet->coin_id = CoinEnums::DefaultUSDTCoinID;
                $spotWallet->save();
            }
            return true;
        });
    }

    public function createUdunWallet(User $user) {
       // 生成u盾钱包地址
       $walletSrv = new UdunService();
       $wallerAddress = [];

       PlatformWallet::all()->each(function ($item) use ($walletSrv, &$wallerAddress, $user) {
           // 生成钱包地址
           $addr = $wallerAddress[$item->udun_main_coin_type] ?? '';
           if (!$addr) {
               $addr = $walletSrv->generateWallet($item->udun_main_coin_type);
               $wallerAddress[$item->udun_main_coin_type] = $addr;
           }

           $userWallet = new UserWalletAddress();
           $userWallet->uid = $user->id;
           $userWallet->platform_wallet_id = $item->id;
           $userWallet->address = $addr;
           $userWallet->save();

           return true;
       }); 
       return;
    }

    
    public function createTestWalletAddr(User $user) {
        PlatformWallet::all()->each(function ($item) use ($user) {
            $userWallet = new UserWalletAddress();
            $userWallet->uid = $user->id;
            $userWallet->platform_wallet_id = $item->id;
            $userWallet->address = 'test_address....';
            $userWallet->save();
            return true;
        });
        return true;
    }
}
