<?php

namespace App\Console\Commands;

use App\Enums\CoinEnums;
use App\Enums\CommonEnums;
use App\Models\PlatformCoin;
use App\Models\Symbol;
use App\Models\SymbolCoin;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Internal\Market\Services\BinanceService;

class InitSymbols extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'symbol:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '初始化交易对';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 初始化 USDT 货币
        $u = new SymbolCoin();
        $u->id = CoinEnums::DefaultUSDTCoinID;
        $u->name = 'USDT';
        $u->block = 'USDT';
        $u->save();


        $symbols = BinanceService::getInstance()->fetchSymbols();
        if (!$symbols) {
            return $this->error('获取交易对失败');
        }

        collect($symbols)->chunk(100)->each(function($items){
            $inserts = [];
            $items->each(function($item) use(&$inserts){
                $baseAsset=strtolower($item['baseAsset']);
                $quoteAsset=strtolower($item['quoteAsset']);

                if ($quoteAsset == 'usdt') {
                    $coin = new SymbolCoin();
                    $coin->name =strtoupper($baseAsset);
                    $coin->block = strtoupper($baseAsset);
                    $coin->save();

                    $symbol = new Symbol();
                    $symbol->name = strtoupper($baseAsset).'/'.strtoupper($quoteAsset);
                    $symbol->coin_id = $coin->id;
                    $symbol->symbol = $baseAsset.$quoteAsset;
                    $symbol->base_asset = $baseAsset;
                    $symbol->quote_asset = $quoteAsset;
                    $symbol->binance_symbol = $item['symbol'];
                    $symbol->digits = $item['quotePrecision'];
                    $symbol->status = CommonEnums::No;
                    $symbol->save();
                    return true;
                }

                $inserts[] = [
                    'name'=> strtoupper($baseAsset).'/'.strtoupper($quoteAsset),
                    'coin_id'=>0,
                    'symbol'=>$baseAsset.$quoteAsset,
                    'base_asset'=>$baseAsset,
                    'quote_asset'=>$quoteAsset,
                    'binance_symbol'=>$item['symbol'],
                    'digits'=>$item['quotePrecision'],
                    'status'=>0,
                    'created_at'=>Carbon::now(),
                ];
                return true;
            });

            if ($inserts) {
                Symbol::insert($inserts);
            }
            return true;
        });
        return $this->info('done');
    }
}
