<?php

namespace App\Console\Commands;

use App\Models\PlatformWallet;
use App\Models\SymbolCoin;
use Illuminate\Console\Command;

class InitPlatformWallet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:platform-init';

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
        $coins = [
            'BTC'=>[
                'udun_name'=>'BTC',
                'udun_coin_type'=>'0',
                'udun_main_coin_type'=>'0',
                'logo'=>'/source/logo/btc.svg',
                'key_name'=>'btc_addr',
                'binance_symbol'=>'BTCUSDT',
            ],

            'ETH'=>[
                'udun_name'=>'ETH',
                'udun_coin_type'=>'60',
                'udun_main_coin_type'=>'60',
                'logo'=>'/source/logo/eth.svg',
                'key_name'=>'eth_addr',
                'binance_symbol'=>'ETHUSDT',
            ],
            'TRX'=>[
                'udun_name'=>'TRX',
                'udun_coin_type'=>'195',
                'udun_main_coin_type'=>'195',
                'logo'=>'/source/logo/trx.svg',
                'key_name'=>'trx_addr',
                'binance_symbol'=>'TRXUSDT',
            ],

            'USDT'=>[
                [
                    'block'=>'TRC20',
                    'udun_name'=>'USDT-TRC20',
                    'udun_coin_type'=>'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                    'udun_main_coin_type'=>'195',
                    'logo'=>'/source/logo/usdt.svg',
                    'key_name'=>'usdt_trc20_addr',
                    'binance_symbol'=>'',
                ],
                [
                    'block'=>'ERC20',
                    'udun_name'=>'USDT-ERC20',
                    'udun_coin_type'=>'0xdac17f958d2ee523a2206206994597c13d831ec7',
                    'udun_main_coin_type'=>'60',
                    'logo'=>'/source/logo/usdt.svg',
                    'key_name'=>'usdt_erc20_addr',
                    'binance_symbol'=>'',
                ],
            ],
        ];

        $allCoins = SymbolCoin::all();
        if ($allCoins->isEmpty()) {
            return $this->error('没有找到货币信息');
        }
        $allCoins = $allCoins->keyBy('name')->toArray();

        foreach($coins as $name=>$c) {
            if ($name == 'USDT') {
                foreach ($c as $i) {
                    $model = new PlatformWallet();
                    $model->name = $name;
                    $model->block = $i['block'];
                    $model->coin_id = $allCoins['USDT']['id'] ?? 0;

                    $model->udun_name = $i['udun_name'];
                    $model->udun_coin_type = $i['udun_coin_type'];
                    $model->udun_main_coin_type = $i['udun_main_coin_type'];
                    $model->binance_symbol = $i['binance_symbol'];
                    $model->save();
                }
                continue;
            }

            $model = new PlatformWallet();
            $model->name = $name;
            $model->block = $name;

            $model->coin_id = $allCoins[strtoupper($name)]['id'] ?? 0;
            $model->udun_name = $c['udun_name'];
            $model->udun_coin_type = $c['udun_coin_type'];
            $model->udun_main_coin_type = $c['udun_main_coin_type'];
            $model->binance_symbol = $c['binance_symbol'];
            $model->save();
        }
        return $this->info('ok');
    }
}
