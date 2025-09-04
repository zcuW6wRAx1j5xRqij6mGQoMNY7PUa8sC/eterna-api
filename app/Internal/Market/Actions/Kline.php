<?php

namespace Internal\Market\Actions;

use App\Enums\CommonEnums;
use App\Enums\IntervalEnums;
use App\Enums\MarketEnums;
use App\Enums\SymbolEnums;
use App\Models\Symbol;
use App\Models\SymbolFutures;
use App\Models\SymbolSpot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Internal\Market\Services\InfluxDB;

/** @package Internal\Market\Actions */
class Kline {

    const CacheKeyAllSymbols = 'data.symbols.%s.all.kline';
    const CacheKeyAllSymbolsTTL = '60 * 60 * 24'; // 24小时缓存

    public function __invoke(Request $request)
    {
        $symbolType = $request->get('symbol_type');
        $interval = $request->get('interval');
        $symbolId = $request->get('symbol_id');

        if ($interval == IntervalEnums::Interval1Month) {
            $interval = IntervalEnums::SpecialInterval1Month;
        }

        $symbol = null;
        $bucket = MarketEnums::SpotInfluxdbBucket;

        if ($symbolType == SymbolEnums::SymbolTypeSpot) {
            $symbol = SymbolSpot::find($symbolId);
        } else {
            $symbol = SymbolFutures::find($symbolId);
        }
        if (!$symbol) {
            return [];
        }
        return (new InfluxDB($bucket))->queryKline($symbol->symbol->binance_symbol,$interval, $this->getQueryStart($interval));
    }

    /**
     * 获取所有交易对的K线
     * @param string $symbolType
     * @return mixed
     */
    public function allSymbolSimpleKline(string $symbolType, array $symbolIds) {
        $data = Cache::remember(sprintf(self::CacheKeyAllSymbols,$symbolType),self::CacheKeyAllSymbolsTTL,function() use($symbolType){
            $bucket = MarketEnums::SpotInfluxdbBucket;
            $symbols = [];
            if ($symbolType == SymbolEnums::SymbolTypeSpot) {
                $symbols = SymbolSpot::with(['symbol'])->where('status', CommonEnums::Yes)->get();
            } else {
                $symbols = SymbolFutures::with(['symbol'])->where('status', CommonEnums::Yes)->get();
            }
            if ($symbols->isEmpty()) {
                return [];
            }

            $binanceSymbols = [];
            $symbols->each(function($item) use(&$binanceSymbols){
                array_push($binanceSymbols, $item->symbol->binance_symbol);
                return true;
            });

            $data = (new InfluxDB($bucket))->queryMultipleKline($binanceSymbols,IntervalEnums::Interval1Day, $this->getQueryStart(IntervalEnums::Interval1Hour));
            if (!$data) {
                return [];
            }
            $klines = [];
            $symbolRange = [];
            foreach($data as $symbol=>$items) {
                $curRage = [];
                foreach ($items as $item) {
                    array_push($curRage, $item['c']);
                    $klines[$symbol][] = [
                        'p'=>$item['c'],
                        't'=>$item['tl'],
                    ];
                }
                $symbolRange[$symbol] = [
                    'min'=>min($curRage),
                    'max'=>max($curRage),
                ];
            }

            return  [
                'items'=>$klines,
                'range'=>$symbolRange
            ];
        });
        return $data;
    }

    private function getQueryStart(string $interval){
        switch ($interval) {
            case IntervalEnums::Interval1Minute:
                return "-1d";
            case IntervalEnums::Interval5Minutes:
                return "-7d";
            case IntervalEnums::Interval15Minutes:
                return "-1mo";
            case IntervalEnums::Interval30Minutes:
                return "-3mo";
            case IntervalEnums::Interval1Hour:
                return "-6mo";
            case IntervalEnums::Interval1Day:
                return "-1y";
            case IntervalEnums::Interval1Week:
                return "-3y";
            case IntervalEnums::Interval1Month:
            case IntervalEnums::SpecialInterval1Month:
                return "-10y";
        }

    }
}
