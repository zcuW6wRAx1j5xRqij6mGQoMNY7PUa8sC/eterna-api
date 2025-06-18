<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserWalletAddress;
use Illuminate\Console\Command;
use Internal\User\Actions\InitUserWallet;

class ChangeUserWalletAddress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:change';

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
        $uids = [
            8089335
        ];

        collect($uids)->each(function($uid){
            UserWalletAddress::where('uid', $uid)->delete();
            $user = User::find($uid);
            (new InitUserWallet)($user);
            $this->info($uid .' : done');
            return true;
        });
        return $this->info('all done');
    }
}