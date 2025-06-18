<?php

namespace Internal\Order\Actions;

use App\Enums\OrderEnums;
use App\Exceptions\LogicException;
use Illuminate\Support\Facades\Log;

class ClosePositionEngineCallback {
    public function __invoke(array $payload)
    {
        $orderId = $payload['order_id'] ?? 0;
        $latestPrice = $payload['price'] ?? 0;
        $closeType = $payload['close_type'] ?? '';

        if (!$orderId) {
            Log::error('处理引擎平仓失败: order id 不正确',['payload'=>$payload]);
            throw new LogicException('处理引擎平仓失败: order id 不正确');
        }   
        if (!$latestPrice) {
            Log::error('处理引擎平仓失败: latest price 不正确',['payload'=>$payload]);
            throw new LogicException('处理引擎平仓失败: latest price 不正确');
        }
        
        if ( ! in_array($closeType,OrderEnums::FuturesCloseTypeAll)) {
            Log::error('处理引擎平仓失败: close type 不正确',['payload'=>$payload]);
            throw new LogicException('处理引擎平仓失败: close type 不正确');
        }
        (new CloseFuturesOrder)($orderId, $latestPrice, $closeType);
        return true;
    }
}