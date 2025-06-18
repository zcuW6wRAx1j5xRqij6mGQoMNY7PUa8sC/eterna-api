<?php

namespace App\Console\Commands;

use App\Enums\FinancialEnums;
use App\Models\UserOrderFinancial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Internal\Financial\Actions\SettlementFinancial;

class SettlementFinancialOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'financial:settlement';

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
        $orders = UserOrderFinancial::where('status', FinancialEnums::StatusPending)->get();
        if ($orders->isEmpty()) {
            $this->info('没有待结算的订单');
            return;
        }

        $orders->each(function($item){
            try {
                (new SettlementFinancial)($item);
            } catch (\Throwable $e) {
                Log::info('结算资金订单失败',[
                    'order_id'=>$item->id
                ]);
            }
            return true;
        });
    }
}
