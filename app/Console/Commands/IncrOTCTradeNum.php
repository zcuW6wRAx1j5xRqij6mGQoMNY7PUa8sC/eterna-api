<?php

namespace App\Console\Commands;

use App\Models\OtcProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class IncrOTCTradeNum extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:incr-otc-trade-num';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '增加otc产品交易量';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $products = OtcProduct::get();
        if ($products->isEmpty()) {
            $this->info(Carbon::now()->toDateTimeString().' 没有OTC产品配置');
            return true;
        }

        foreach ($products as $product) {
            $product->total_count   += 12345;
            $product->total_amount  *= 1.02;
            $product->save();
        }

        return $this->info('done');
    }
}
