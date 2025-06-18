<?php

namespace Internal\Market\Actions;

use App\Enums\CommonEnums;
use App\Enums\MarketEnums;
use App\Models\Symbol;

class FetchSymbolFuturesQuote {

    public function __invoke(string $symbol)
    {
        return RedisMarket()->get(sprintf(MarketEnums::FuturesSymbolQuoteCacheKey, strtolower($symbol)));
    }

    // 获取所有报价
    public function getAllSymbol() {
        $keys = [];
        Symbol::where('status', CommonEnums::Yes)->get()->each(function($item) use(&$keys){
            array_push($keys , strtolower($item->binance_symbol));
            return true;
        });

        if (!$keys) {
            return [];
        }

        $redis = RedisMarket();
        $data = $redis->pipeline(function($pipe) use($keys){
            foreach ($keys as $key) {
                $pipe->get(sprintf(MarketEnums::FuturesSymbolQuoteCacheKey, $key));
            }
        });
        $symbolQuotes = [];
        foreach ($keys as $key) {
            $symbolQuotes[$key] = !$data ? 0 : array_shift($data);
        }
        return $symbolQuotes;
    }
}
