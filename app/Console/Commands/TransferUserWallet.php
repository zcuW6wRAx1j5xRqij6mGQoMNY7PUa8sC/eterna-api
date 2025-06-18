<?php

namespace App\Console\Commands;

use App\Enums\CoinEnums;
use App\Models\User;
use App\Models\UserWalletFutures;
use App\Models\UserWalletSpot;
use Illuminate\Console\Command;
use Internal\Wallet\Actions\Transfer;

class TransferUserWallet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:transfer-user-wallet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = User::find(8089159);
        $spotWallet = UserWalletSpot::where('uid', $user->id)->where('coin_id',CoinEnums::DefaultUSDTCoinID)->lockForUpdate()->first();
        if (!$spotWallet) {
            return $this->error('现货账户不正确');
        }
        $derivativeWallet = UserWalletFutures::where('uid', $user->id)->first();
        if (!$derivativeWallet) {
            return $this->error('合约账户不正确');
        }
        (new Transfer)->toSpotWallet($user, $derivativeWallet, $spotWallet, 500);
        return $this->info('done');
    }
}
