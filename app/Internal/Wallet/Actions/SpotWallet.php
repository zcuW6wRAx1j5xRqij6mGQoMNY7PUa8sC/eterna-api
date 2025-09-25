<?php

namespace Internal\Wallet\Actions;

use App\Enums\CoinEnums;
use App\Http\Resources\UserWalletSpotCollection;
use App\Models\SymbolCoin;
use App\Models\User;
use App\Models\UserWalletSpot;

class SpotWallet {
    
    public function __invoke(User $user, int $isOtc = 0)
    {
        (new UpdateSpotWalletUsdt)($user);
        $coins = UserWalletSpot::with(['coin'])->where('uid', $user->id)->get();
        $alias = 'user_wallet_spot.';
        $query = UserWalletSpot::query()
                               ->leftJoin('symbol_coins as sc', 'sc.id', '=', $alias . 'coin_id')
                               ->where($alias . 'uid', $user->id);
        if ($isOtc) {
            $query->whereNot('sc.block', CoinEnums::COIN_ULX);
        }
        
        $totalAssets = $query->sum($alias . 'usdt_value');
        $usdtWallet  = UserWalletSpot::query()
                                     ->where('uid', $user->id)
                                     ->where('coin_id', CoinEnums::DefaultUSDTCoinID)
                                     ->first();
        
        return [
            'usdt'  => $usdtWallet ? $usdtWallet->amount : 0,
            'total' => $totalAssets ?? 0,
            'coins' => new UserWalletSpotCollection($coins),
        ];
    }
    
    public function selector(User $user)
    {
        (new UpdateSpotWalletUsdt)($user);
        $coins = UserWalletSpot::query()->where('uid', $user->id)
                               ->orderByDesc('usdt_value')
                               ->pluck('amount', 'coin_id')
                               ->toArray();
        $rows  = SymbolCoin::query()->join('platform_wallet', 'platform_wallet.coin_id', '=', 'symbol_coins.id', 'left')
                           ->select('symbol_coins.id AS coin_id', 'symbol_coins.logo', 'symbol_coins.name')
                           ->groupBy('symbol_coins.id')
                           ->orderBy('symbol_coins.sort')
                           ->get();
        foreach ($rows as $row) {
            $exists               = array_key_exists($row->coin_id, $coins);
            $coins[$row->coin_id] = [
                'coin_id'   => $row->coin_id,
                'coin_name' => $row->name,
                'logo'      => $row->logo,
                'amount'    => $exists ? $coins[$row->coin_id] : '0.00000000',
            ];
        }
        
        return array_values($coins);
    }
}

