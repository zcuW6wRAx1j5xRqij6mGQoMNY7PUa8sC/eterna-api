<?php

namespace App\Internal\Order\Actions;

use App\Enums\CommonEnums;
use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Events\NewPledgeOrder as AuditPledgeOrderEvent;
use App\Exceptions\LogicException;
use App\Models\OtcOrder;
use App\Models\OtcProduct;
use App\Models\UserOrderPledge;
use App\Models\UserWalletPledgeFlow;
use App\Models\UserWalletSpot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AuditOtcOrder
{

    public function __invoke(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $id     = $request->get('id');
            $status = $request->get('status');
            $order  = OtcOrder::find($id);
            if(!$order){
                throw new InvalidArgumentException(__('Order not found'));
            }

            $product = OtcProduct::where('id', $order->product_id)->lockForUpdate()->first();
            if(!$product){
                throw new InvalidArgumentException(__('Product not found'));
            }

            if($order->status != OrderEnums::TradeStatusPending){
                throw new LogicException(__('Trade status is not pending'));
            }

            if($status == OrderEnums::TradeStatusAccepted) {
                $product->total_count++;//成单数量
//                $product->success_rate = 99;//成单率
                $product->total_amount += $order->amount;
                $product->save();
            }

            $order->auditor     = $request->user()->id;
            $order->audit_at    = carbon::now()->toDateTimeString();
            $order->status      = $status;
            $order->save();

            return true;
        });
    }

}
