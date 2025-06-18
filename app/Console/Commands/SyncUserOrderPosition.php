<?php

namespace App\Console\Commands;

use App\Enums\OrderEnums;
use App\Models\UserOrderFutures;
use Illuminate\Console\Command;
use Internal\Order\Actions\MonitorPosition;

class SyncUserOrderPosition extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'futures:sync-order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步用户订单到Redis';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $order = UserOrderFutures::where('trade_status', OrderEnums::FuturesTradeStatusOpen)->get();
        if ($order->isEmpty()) {
            return $this->info('没有发现持仓中订单');
        }
        $order->each(function($item) {
            (new MonitorPosition)($item);
            $this->info('成功推送订单到redis order_id :'. $item->id);
        });

        return $this->info('done');
    }
}
