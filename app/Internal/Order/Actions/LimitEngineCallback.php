<?php

namespace Internal\Order\Actions;

use App\Enums\SymbolEnums;
use App\Exceptions\LogicException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LimitEngineCallback {

    public function __invoke(array $payload)
    {
        $orderType = $payload['order_type'] ?? '';
        $orderIds = $payload['order_ids'] ?? [];
        $latestPrice = $payload['price'] ?? 0;

        if ( ! in_array($orderType, [SymbolEnums::SymbolTypeFutures, SymbolEnums::SymbolTypeSpot])) {
            Log::error('处理挂单处理失败 , orderType 不正确',['payload'=>$payload]);
            throw new LogicException('处理挂单处理失败 , orderType 不正确');
        }
        if (!$orderIds) {
            Log::error('处理挂单处理失败 , order id 不正确',['payload'=>$payload]);
            throw new LogicException('处理挂单处理失败 , order ids 不正确');
        }
        if (!$latestPrice) {
            Log::error('处理挂单处理失败 , price 不正确',['payload'=>$payload]);
            throw new LogicException('处理挂单处理失败 , price 不正确');
        }

        if ($orderType == SymbolEnums::SymbolTypeSpot) {
            foreach ($orderIds as $orderId) {
                try {
                    (new CreateSpotOrder)->handelLimit($orderId, $latestPrice);
                } catch(\Throwable $e) {

                }
            }
        } else {
            foreach ($orderIds as $orderId) {
                try {
                    (new CreateFuturesOrder)->handelLimit($orderId, $latestPrice);
                } catch(\Throwable $e) {
                }
            }
        }
        return true;
    }
}
