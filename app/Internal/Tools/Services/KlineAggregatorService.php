<?php
declare(strict_types=1);

namespace App\Internal\Tools\Services;

use Carbon\CarbonImmutable;

/**
 * 将“秒K线”聚合为多周期K线（1m/5m/15m/30m/1h/1d）
 * 以第一条数据的 timestamp(ms) 作为起点对齐，不按自然分钟/小时/日对齐。
 *
 * 输入秒K支持字段: timestamp(ms), o, h, l, c
 * 也兼容 open/high/low/close 或只有 price（将同时作为 o/h/l/c）
 */
final class KlineAggregatorService {
    /**
     * @param array $rows      秒K数组或Collection
     *                         每项示例: ['timestamp'=>1723245600000,'o'=>101.2,'h'=>101.7,'l'=>100.9,'c'=>101.5]
     * @param array $intervals 需要聚合的周期标签
     *
     * @return array<string,array<int,array<string,mixed>>>
     *   返回结构: ['1m'=>[['t'=>ms,'time'=>iso,'o'=>...,'h'=>...,'l'=>...,'c'=>...],...], '5m'=>[...], ...]
     */
    public static function aggregate(array $rows, array $intervals = ['1m', '5m', '15m', '30m', '1h', '1d', '1w', '1M']): array
    {
//        if ($rows instanceof Collection) {
//            $rows = $rows->all();
//        }
        if (empty($rows)) {
            return [];
        }
        
        // 按时间升序，确保以第一条为起点
        usort($rows, static fn($a, $b) => (int)($a['timestamp'] ?? 0) <=> (int)($b['timestamp'] ?? 0));
        
        $t0Ms = (int)($rows[0]['timestamp'] ?? 0);
        $t0   = CarbonImmutable::createFromTimestampMs($t0Ms);
        
        // 周期对应的毫秒数
        $allPeriods = [
            '1m'  => 60_000,
            '5m'  => 5 * 60_000,
            '15m' => 15 * 60_000,
            '30m' => 30 * 60_000,
            '1h'  => 60 * 60_000,
            '1d'  => 24 * 60 * 60_000,
            '1w'  => 7 * 24 * 60 * 60_000,
            '1M'  => 30 * 24 * 60 * 60_000,
        ];
        // 仅保留请求的周期
        $periods = array_intersect_key($allPeriods, array_flip($intervals));
        if (empty($periods)) {
            return [];
        }
        
        // 结果桶
        // key = bucketStartMs, value = K线
        $buckets = array_map(function ($ms) {
            return [];
        }, $periods);
        
        foreach ($rows as $row) {
            $tMs = (int)($row['timestamp'] ?? 0);
            
            // 如果仍拿不到有效价格，跳过
            if (!is_numeric($row['open']) || !is_numeric($row['high']) || !is_numeric($row['low']) || !is_numeric($row['close'])) {
                continue;
            }
            
            foreach ($periods as $label => $periodMs) {
                // 以第一条时间为起点进行“偏移对齐”
                $index         = intdiv($tMs - $t0Ms, $periodMs);          // 第几个周期
                $bucketStart   = $t0->addMilliseconds($index * $periodMs); // Carbon 生成开始时间
                $bucketStartMs = $bucketStart->getTimestampMs();
                
                if (!isset($buckets[$label][$bucketStartMs])) {
                    $buckets[$label][$bucketStartMs] = [
                        'timestamp' => $bucketStartMs,
                        'open'      => (float)$row['open'],
                        'high'      => (float)$row['high'],
                        'low'       => (float)$row['low'],
                        'close'     => (float)$row['close'],
                    ];
                } else {
                    $kline = &$buckets[$label][$bucketStartMs];
                    // open 保持首笔
                    $kline['high']  = max($kline['high'], (float)$row['high']);
                    $kline['low']   = min($kline['low'], (float)$row['low']);
                    $kline['close'] = (float)$row['close'];  // close 为该桶内最后一笔
                    unset($kline);                           // 释放引用
                }
            }
        }
        
        // 输出按时间排序的数组
        $out = [];
        foreach ($buckets as $label => $map) {
            ksort($map);
            $out[$label] = array_values($map);
        }
        
        return $out;
    }
    
    /**
     * （可选）用前一根的收盘价填充缺失K（若需要连续序列）
     *
     * @param array<int,array{t:int,time:string,o:float,h:float,l:float,c:float}> $bars
     * @param int                                                                 $periodMs
     *
     * @return array<int,array{t:int,time:string,o:float,h:float,l:float,c:float}>
     */
    public static function fillGaps(array $bars, int $periodMs): array
    {
        if (empty($bars)) {
            return $bars;
        }
        
        $filled   = [];
        $prev     = $bars[0];
        $filled[] = $prev;
        
        for ($i = 1, $n = count($bars); $i < $n; $i++) {
            $expect = $prev['ttimestamp'] + $periodMs;
            while ($expect < $bars[$i]['ttimestamp']) {
                // 以上一根的 close 复制一根空K
                $c        = $prev['close'];
                $time     = CarbonImmutable::createFromTimestampMs($expect)->toIso8601String();
                $filled[] = ['ttimestamp' => $expect, 'time' => $time, 'open' => $c, 'high' => $c, 'low' => $c, 'close' => $c];
                $expect   += $periodMs;
            }
            $filled[] = $bars[$i];
            $prev     = $bars[$i];
        }
        
        return $filled;
    }
}
