<?php

namespace Internal\Order\Actions;

use App\Enums\OrderEnums;
use App\Models\UserDerivativelPosition;
use App\Models\UserOrderFutures;

class MonitorPosition {

    public function __invoke(UserOrderFutures $userOrderFutures)
    {
        $redis = RedisPosition();
        $data = [
            'i'=>(string)$userOrderFutures->id,
            'u'=>(string)$userOrderFutures->uid,
            'm'=>floatTransferString( $userOrderFutures->margin),
            'mt'=>(string)$userOrderFutures->margin_type,
            'o'=>floatTransferString($userOrderFutures->open_price),
            'v'=>floatTransferString($userOrderFutures->volume),
            'sp'=>floatTransferString($userOrderFutures->futures->sell_spread ?? 0),
            's'=>(string)$userOrderFutures->side,
            'sl'=>floatTransferString($userOrderFutures->sl ?? 0),
            'tp'=>floatTransferString($userOrderFutures->tp ?? 0),
        ];
        $key = sprintf(OrderEnums::DerivativeOrderMonitorKey, strtolower($userOrderFutures->symbol->binance_symbol));
        $redis->hset($key,$userOrderFutures->id,json_encode($data));

        // 用户的仓位信息
        if ($userOrderFutures->margin_type == OrderEnums::MarginTypeCrossed) {
            $positionKey = sprintf(OrderEnums::FuturesUserPotionsKey, $userOrderFutures->uid);
            $redis->hset($positionKey, $userOrderFutures->id, json_encode(['pt'=>0]));
        }
        return true;
    }

    /**
     * 取消监控
     * @param UserDerivativelPosition $userDerivativelPosition
     * @return true
     */
    public function cancel(UserOrderFutures $userOrderFutures) {
        $key = sprintf(OrderEnums::DerivativeOrderMonitorKey, strtolower($userOrderFutures->symbol->binance_symbol));
        $redis = RedisPosition();
        $redis->hdel($key, $userOrderFutures->id);

        $positionKey = sprintf(OrderEnums::FuturesUserPotionsKey, $userOrderFutures->uid);
        $redis->del($positionKey, $userOrderFutures->id);
        return true;
    }
}

