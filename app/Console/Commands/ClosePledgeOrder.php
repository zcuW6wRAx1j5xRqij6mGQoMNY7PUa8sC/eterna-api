<?php

namespace App\Console\Commands;

use App\Enums\CommonEnums;
use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Models\User;
use App\Models\UserOrderPledge;
use App\Models\UserWalletPledgeFlow;
use App\Models\UserWalletSpot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ClosePledgeOrder extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:close-pledge';
    
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
        $orders = UserOrderPledge::class::select('user_order_pledge.*', 'ws.id as usdc_wallet_id')
                                        ->join('user_wallet_spot AS ws', function ($join) {
                                            $join->on('user_order_pledge.uid', '=', 'ws.uid')
                                                 ->where('ws.coin_id', '=', CommonEnums::USDCCoinID);
                                        })
                                        ->where('user_order_pledge.status', OrderEnums::PledgeTradeStatusHold)
                                        ->where('user_order_pledge.end_at', date('Y-m-d'))
                                        ->get();
        
        foreach ($orders as $order) {
            $this->deal($order);
        }
        
        return $this->info(Carbon::now()->toDateTimeString() . ' done' . PHP_EOL);
    }
    
    public function deal($order)
    {
        // 收回usdc
        $wallet = UserWalletSpot::where('id', $order->usdc_wallet_id)->lockForUpdate()->first();
        if (!$wallet) {
            // 不存在
            Log::error('failed to create pledge order : no found user wallet', [
                'uid'       => $order->id,
                'wallet_id' => $order->wallet_id,
            ]);
            return false;
        }
        
        $d = bcsub($wallet->amount, $order->quota, FundsEnums::DecimalPlaces);
        if ($d < 0) {
            return $this->fail($order);
        }
        
        $before         = $wallet->amount;
        $wallet->amount = $d;
        $wallet->save();
        
        // 增加流水信息
        $flow                = new UserWalletPledgeFlow();
        $flow->uid           = $order->uid;
        $flow->coin_id       = CommonEnums::USDCCoinID;
        $flow->flow_type     = OrderEnums::PledgeTradeStatusClosed;
        $flow->before_amount = $before;
        $flow->amount        = -$order->quota;
        $flow->after_amount  = $wallet->amount;
        $flow->relation_id   = $order->id;
        $flow->save();
        
        
        // 退还原始币种金额
        $wallet = UserWalletSpot::where('uid', $order->uid)->where('coin_id', $order->coin_id)->lockForUpdate()->first();
        if (!$wallet) {
            // 不存在
            Log::error('failed to create pledge order : no found user wallet', [
                'uid'     => $order->id,
                'coin_id' => $order->coin_id,
            ]);
            return false;
        }
        
        $d = bcadd($wallet->amount, $order->amount, FundsEnums::DecimalPlaces);
        
        $before              = $wallet->amount;
        $wallet->amount      = $d;
        $wallet->lock_amount = bcsub($wallet->lock_amount, $order->amount, FundsEnums::DecimalPlaces);
        $wallet->save();
        
        // 增加流水信息
        $flow                = new UserWalletPledgeFlow();
        $flow->uid           = $order->uid;
        $flow->coin_id       = $order->coin_id;
        $flow->flow_type     = OrderEnums::PledgeTradeStatusClosed;
        $flow->before_amount = $before;
        $flow->amount        = $order->amount;
        $flow->after_amount  = $wallet->amount;
        $flow->relation_id   = $order->id;
        $flow->save();
        
        $order->status           = OrderEnums::PledgeTradeStatusClosed;
        $order->principal_remain = $order->amount;
        $order->redeem_remain    = 0;
        $order->closed_at        = Carbon::now();
        $order->save();
        
        $user = User::find($order->uid);
        if (!$user) {
            return false;
        }
        $user->funds_lock = CommonEnums::No;
        $user->save();
        
        return true;
    }
    
    
    private function fail($order)
    {
        $order->status = OrderEnums::PledgeTradeStatusClosing;
        $order->save();
        
        $user = User::find($order->uid);
        if (!$user) {
            return false;
        }
//        $user->remark = '质押到期, 无钱赎回';
        $user->remark     = $user->remark . PHP_EOL . '质押到期';
        $user->funds_lock = CommonEnums::Yes;
        $user->save();
        
        return true;
    }
    
}
