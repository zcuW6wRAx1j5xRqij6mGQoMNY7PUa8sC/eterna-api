<?php

namespace App\Console\Commands;

use App\Enums\CoinEnums;
use App\Models\PlatformCoin;
use App\Models\PlatformWallet;
use App\Models\User;
use App\Models\UserSpotWalletCoin;
use App\Models\UserWalletAddress;
use App\Models\UserWalletFutures;
use App\Models\UserWalletSpot;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Internal\User\Actions\InitUserWallet;

class GenerateUserWallet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:wallet-init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成用户钱包';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user = User::find(8088806);
        (new InitUserWallet)($user);
        $this->info('done');
    }
}
