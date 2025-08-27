<?php

namespace App\Jobs;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Internal\Market\Services\InfluxDB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Internal\Tools\Services\GbmPathService;

class CreateCoinHistoryData implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    /**
     * Create a new job instance.
     */
    public function __construct(public array $options) {}
    
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 按 YYYY-mm-dd H:i:s 生成时间数据
        $days    = $this->calcDays($this->options['start_time'], $this->options['end_time']);
        $maxStep = count($days) - 1;
        // 生成价格
        $prices  = GbmPathService::generateCandles(
            startOpen: $this->options['open'],
            endClose: $this->options['close'],
            startTime: $this->options['start_time'],
            endTime: $this->options['end_time'],
            targetHigh: $this->options['high'],
            targetLow: $this->options['low'],
            sigma: $this->options['sigma'],
            intervalSeconds: 86400,
            scale: $this->options['scale'],
            getPrices: true,
            maxStep: $maxStep
        );
        $minutes = [];
        $redis   = Redis::connection();
        // 按天生成每秒价格
        for ($i = 0; $i < count($prices) - 1; $i++) {
            $open       = $prices[$i];
            $close      = $prices[$i + 1];
            $maxOffset  = rand(0, (int)((($this->options['close'] - $open) / 2) * 10000)) / 10000;
            $maxOffsets = rand(0, (int)((($this->options['high'] - $open) / 2) * 10000)) / 10000;
            $high       = $open < $this->options['close'] ? $open + abs($maxOffset) : $open + abs($maxOffsets);
            $high       = max($high, $close);
            $minOffset  = rand(0, (int)((($this->options['close'] - $close) / 2) * 10000)) / 10000;
            $minOffset2 = rand(0, (int)((($close - $this->options['close']) / 2) * 10000)) / 10000;
            $low        = $close < $this->options['close'] ? $close - abs($minOffset) : $close - abs($minOffset2);
            if ($low < $this->options['low']) {
                $low = $this->options['low'];
            } else if ($low > $open) {
                $low = $open;
            }
            
            $kline   = GbmPathService::generateCandles(
                startOpen: $open,
                endClose: $close,
                startTime: $days[$i],
                endTime: $days[$i + 1],
                targetHigh: $high,
                targetLow: $low,
                sigma: $this->options['sigma'],
                scale: $this->options['scale'],
                short: true
            );
            $data    = $this->aggregates($kline, [$this->options['unit']]);
            $minutes = $data[$this->options['unit']];
            // 使用 redis 管道批量写入数据库
            $redis->pipeline(function ($pipe) use ($minutes) {
                foreach ($minutes as $minute) {
                    $pipe->zadd($this->options['symbol'] . ":" . $this->options['unit'], $minute['tl'], json_encode($minute));
                }
            });
            Log::info(Carbon::createFromTimestamp($minutes[0]['tl'] / 1000, config('app.timezone'))->toDateTimeString() . ' 数量：' . count($minutes));
//            $service = new InfluxDB('market_spot');
//            if ($this->options['is_del']) {
//                $service->deleteData($this->options['symbol']);
//                $this->options['is_del'] = 0;
//            }
//            $service->writeData($this->options['symbol'], $this->options['unit'], $minutes);
        }
    }
    
    public function calcDays(string $start, string $end): array|int
    {
        $start         = Carbon::parseFromLocale($start);
        $end           = Carbon::parseFromLocale($end);
        $endOfFirstDay = $start->copy()->endOfDay();
        if ($end->copy()->isBefore($endOfFirstDay)) {
            return 1;
        }
        $timeData     = [];
        $startDate    = $start->copy()->addDay()->startOfDay();
        $endOfLastDay = $end->copy()->startOfDay();
        $timeData[]   = $start->copy()->toDateTimeString();
        if ($startDate->copy()->isBefore($endOfLastDay)) {
            $days = $startDate->copy()->diffInDays($endOfLastDay);
            for ($i = 0; $i <= $days; $i++) {
                $timeData[] = $startDate->copy()->toDateTimeString();
                if ($i < $days) {
                    $startDate->addDay();
                }
            }
        } else {
            $timeData[] = $startDate->copy()->toDateTimeString();
        }
        if ($startDate->copy()->isBefore($end)) {
            $timeData[] = $end->copy()->toDateTimeString();
        }
        return $timeData;
    }
    
    private function aggregates(array $rows, array $intervals = ['1m', '5m', '15m', '30m', '1h', '1d', '1w', '1M']): array
    {
        if (empty($rows)) {
            return [];
        }
        
        // 按时间升序，确保以第一条为起点
        usort($rows, static fn($a, $b) => (int)($a['tl'] ?? 0) <=> (int)($b['tl'] ?? 0));
        
        $t0Ms = (int)($rows[0]['tl'] ?? 0);
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
            $tMs = (int)($row['tl'] ?? 0);
            
            // 如果仍拿不到有效价格，跳过
            if (!is_numeric($row['o']) || !is_numeric($row['h']) || !is_numeric($row['l']) || !is_numeric($row['c'])) {
                continue;
            }
            
            foreach ($periods as $label => $periodMs) {
                // 以第一条时间为起点进行“偏移对齐”
                $index         = intdiv($tMs - $t0Ms, $periodMs);          // 第几个周期
                $bucketStart   = $t0->addMilliseconds($index * $periodMs); // Carbon 生成开始时间
                $bucketStartMs = $bucketStart->getTimestampMs();
                
                if (!isset($buckets[$label][$bucketStartMs])) {
                    $buckets[$label][$bucketStartMs] = [
                        'tl' => $bucketStartMs,
                        'o'  => (float)$row['o'],
                        'h'  => (float)$row['h'],
                        'l'  => (float)$row['l'],
                        'c'  => (float)$row['c'],
                        'v'  => (int)$row['v'],
                    ];
                } else {
                    $kline = &$buckets[$label][$bucketStartMs];
                    // open 保持首笔
                    $kline['h'] = max($kline['h'], (float)$row['h']);
                    $kline['l'] = min($kline['l'], (float)$row['l']);
                    $kline['c'] = (float)$row['c'];
                    $kline['v'] += $row['v'];
                    unset($kline);
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
}
