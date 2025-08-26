<?php

namespace App\Internal\Tools\Services;

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
class WriteKlineService
{

    public function __invoke(Request $request)
    {
        $symbolType = $request->get('symbol_type');
        $interval   = $request->get('interval');
        $symbolId   = $request->get('symbol_id');
        $startTime  = $request->get('start_time');//开始时间
        $endTime    = $request->get('end_time');//结束时间

        if ($interval == IntervalEnums::Interval1Month) {
            $interval = IntervalEnums::SpecialInterval1Month;
        }

        $bucket = MarketEnums::FuturesInfluxdbBucket;
        if ($symbolType == SymbolEnums::SymbolTypeSpot) {
            $bucket = MarketEnums::SpotInfluxdbBucket;
            $symbol = SymbolSpot::find($symbolId);
        } else {
            $symbol = SymbolFutures::find($symbolId);
        }
        if (!$symbol) {
            return [];
        }

        $initialPrice = 0.2626;//初始开盘价
        $finalPrice   = 0.2594;//最终收盘价

        $klines = $this->generateKlineData($startTime, $endTime, $initialPrice, $finalPrice);

        return (new InfluxDB($bucket))->writeMultiData($symbol->symbol->binance_symbol, $klines, $interval);
    }

    function generateKlineData($startTime, $endTime, $initialPrice, $finalPrice)
    {
        // 将时间转换为时间戳
        $startTimestamp = strtotime($startTime);
        $endTimestamp   = strtotime($endTime);

        // 计算总分钟数
        $totalMinutes = ($endTimestamp - $startTimestamp) / 60;

        // 计算每分钟的平均价格变化
        $priceChangePerMinute = ($finalPrice - $initialPrice) / $totalMinutes;

        // 初始化结果数组
        $klineData    = [];
        $currentPrice = $initialPrice;
        $currentTime  = $startTimestamp;

        // 生成每分钟的K线数据
        while ($currentTime <= $endTimestamp) {
            // 使用正态分布生成随机波动
            // 标准差设为价格变化的1/3，这样大部分波动在合理范围内
            $volatility   = $priceChangePerMinute / 3;
            $randomChange = gauss_random() * $volatility;

            // 应用趋势和随机波动
            $nextPrice = $currentPrice + $priceChangePerMinute + $randomChange;

            // 确保价格不为负
            $nextPrice = max(0.0001, $nextPrice);

            // 在当前价格和下一价格之间生成OHLC
            $open  = $currentPrice;
            $close = $nextPrice;

            // 随机生成高低点，确保在open和close的范围内有波动
            $range = abs($close - $open);
            $high  = max($open, $close) + $range * 0.5 * mt_rand(0, 100) / 100;
            $low   = min($open, $close) - $range * 0.5 * mt_rand(0, 100) / 100;

            // 确保高低点合理
            $high = max($open, $close, $high);
            $low  = min($open, $close, $low);

            // 添加K线数据
            $klineData[] = [
                'time'   => $currentTime * 1000,
                'open'   => round($open, 6),
                'high'   => round($high, 6),
                'low'    => round($low, 6),
                'close'  => round($close, 6),
                'volume' => mt_rand(900, 1000) * mt_rand(min($open*100, $close*10)/100, max($open*100, $close*100)/100), // 随机生成交易量
                'count'  => mt_rand(900, 1000) * 60 // 随机生成交易量
            ];

            // 更新时间
            $currentTime  += 60;
            $currentPrice = $close;
        }

        // 调整最后一个K线的收盘价确保与最终价格一致
        if (!empty($klineData)) {
            $klineData[count($klineData) - 1]['close'] = round($finalPrice, 6);
            // 可能需要调整high或low
            $lastKline         = &$klineData[count($klineData) - 1];
            $lastKline['high'] = max($lastKline['high'], $lastKline['close']);
            $lastKline['low']  = min($lastKline['low'], $lastKline['close']);
        }

        return $klineData;
    }

    // 正态分布随机数生成函数
    function gauss_random()
    {
        static $useExists = false;
        static $value;

        if ($useExists) {
            $useExists = false;
            return $value;
        } else {
            $u1        = mt_rand() / mt_getrandmax();
            $u2        = mt_rand() / mt_getrandmax();
            $value     = sqrt(-2 * log($u1)) * cos(2 * pi() * $u2);
            $useExists = true;
            return sqrt(-2 * log($u1)) * sin(2 * pi() * $u2);
        }
    }

}
