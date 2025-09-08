<?php

namespace App\Internal\Order\Actions;

use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Events\NewOtcOrder;
use App\Models\OtcOrder;
use App\Models\OtcProduct;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Illuminate\Http\Request;

class CreateOtcOrder {
    
    public function __invoke(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $uid           = $request->user()->id;
            $productId     = $request->get('product_id');
            $quantity      = $request->get('quantity');
            $paymentMethod = $request->get('payment_method');
            $comments      = $request->get('comments');
            $tradeType     = $request->get('trade_type');
            
            $quantity = parseNumber($quantity);
            if ($quantity <= 0) {
                throw new InvalidArgumentException(__('Quantity is invalid'));
            }
            
            $product = OtcProduct::find($productId);
            if (!$product) {
                throw new InvalidArgumentException(__('Product is not exist'));
            }
            
            if ($tradeType == OrderEnums::TradeTypeBuy) {
                if ($quantity < $product->min_limit || $quantity > $product->max_limit) {
                    throw new InvalidArgumentException(__('Amount is out of range'));
                }
            } else {
                if ($quantity < $product->sell_min_limit || $quantity > $product->sell_max_limit) {
                    throw new InvalidArgumentException(__('Amount is out of range'));
                }
            }
            
            $price = $tradeType == OrderEnums::TradeTypeBuy ? $product->buy_price : $product->sell_price;
            
            $order                 = new OtcOrder();
            $order->uid            = $uid;
            $order->product_id     = $productId;
            $order->quantity       = $quantity;
            $order->amount         = bcmul($quantity, $price, FundsEnums::DecimalPlaces);
            $order->payment_method = $paymentMethod;
            $order->trade_type     = $tradeType;
            $order->comments       = (string)$comments;
            if ($tradeType == OrderEnums::TradeTypeBuy) {
                $order->buy_price = $price;
            } else {
                $order->sell_price = $price;
            }
            $order->status = OrderEnums::TradeStatusPending;
            $order->save();
            
            NewOtcOrder::dispatch($order);
            return true;
        });
    }
    
}
