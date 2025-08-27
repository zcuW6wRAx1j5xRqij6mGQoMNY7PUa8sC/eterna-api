<?php

namespace App\Internal\Tools\Services;

use Carbon\CarbonImmutable;
use App\Exceptions\LogicException;
use Exception;
use Throwable;
use Carbon\Carbon;
use App\Models\Symbol;
use App\Enums\CommonEnums;
use Illuminate\Support\Facades\Cache;
use Internal\Market\Services\InfluxDB;
use App\Models\BotTask as ModelsBotTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BotTask {
    const TaskCommandQueueName = "bot.task";
    
    // TaskCommandTypeNewTask 任务指令类型 : 新任务
    const TaskCommandTypeNewTask = "new_task";
    // TaskCommandTypeStopTask 任务指令类型 : 停止任务
    const TaskCommandTypeStopTask = "stop_task";
    // TaskCommandTypeFreeFloat 任务指令类型 : 更换每日自由浮动率
    const TaskCommandTypeFreeFloat = "free_float";
    
    
    // golang 结构体定义
    // Type   string  `json:"type"`
    // ID     string  `json:"id,omitempty"`
    // Symbol string  `json:"symbol,omitempty"`
    // Start  string  `json:"start,omitempty"`
    // End    string  `json:"end,omitempty"`
    // High   float64 `json:"high,omitempty"`
    // Low    float64 `json:"low,omitempty"`
    // Close  float64 `json:"close,omitempty"`
    // Bound  float64 `json:"bound,omitempty"`
    
    
    public function __invoke() {}
    
    public function changeFloat($symbol, $bound)
    {
        RedisMarket()->publish(self::TaskCommandQueueName, json_encode([
            'type'   => self::TaskCommandTypeFreeFloat,
            'symbol' => strtoupper($symbol),
            'bound'  => $bound,
        ]));
        return true;
    }
    
    public function newTask(ModelsBotTask $task)
    {
        RedisMarket()->publish(self::TaskCommandQueueName, json_encode([
            'type'   => self::TaskCommandTypeNewTask,
            //            'id'=>(string)$task->id,
            'symbol' => strtoupper($task->symbol->symbol),
            //            'start'=>$task->start_at,
            //            'end'=>$task->end_at,
            //            'high'=>(float)$task->high,
            //            'low'=>(float)$task->low,
            //            'close'=>(float)$task->close
        ]));
        return true;
    }
    
    public function stopTask(ModelsBotTask $task)
    {
        $symbol = strtoupper($task->symbol->symbol);
        
        $start = strtotime($task->start_at);
        $end   = strtotime($task->end_at) + 1;
        
        $queueKey  = sprintf(config('kline.queue_key'), $symbol);
        $cachedata = RedisMarket()->get($queueKey);
        $cachedata = $cachedata ? json_decode($cachedata, true) : [];
        $cachedata = $cachedata ?: [];
        
        if ($cachedata) {
            for ($timestamp = $start; $timestamp < $end; $timestamp++) {
                if (array_key_exists($timestamp, $cachedata)) {
                    unset($cachedata[$timestamp]);
                }
            }
        }
        if (count($cachedata) > 0) {
            RedisMarket()->set($queueKey, json_encode($cachedata));
        } else {
            RedisMarket()->delete($queueKey);
        }
        RedisMarket()->publish(self::TaskCommandQueueName, json_encode([
            'type'   => self::TaskCommandTypeStopTask,
            'id'     => (string)$task->id,
            'symbol' => $symbol,
        ]));
        return true;
    }
    
    public function createTask(
        int    $uid,
        int    $coinId,
        string $coinType,
        float  $open,
        float  $targetHigh,
        float  $targetLow,
        float  $close,
        string $startTime,
        string $endTime,
        float  $sigma = 0.0003
    )
    {
        // 获取请求中的币种ID和计算明天的开始和结束时间
        $coinID = $coinId;
        
        // 开始数据库事务，确保数据一致性
        DB::beginTransaction();
        
        try {
            // 构建查询条件以验证交易对信息
            $where = [
                'id'     => $coinID,
                'status' => CommonEnums::Yes,
            ];
            // 获取交易对信息
            $info = Symbol::where($where)->first();
            // 如果交易对不存在，则记录错误日志并返回错误响应
            if (!$info) {
                Log::error('Coin Not Found');
                return $this->fail(__('Coin Not Found'));
            }
            $symbol = strtoupper($info->symbol);
            $open   = $open <= 0 ? 0.0001 : $open;
            $data   = GbmPathService::generateCandles(
                $open,
                $close,
                $startTime,
                $endTime,
                $targetHigh,
                $targetLow,
                $sigma
            );
            
            $task = [
                'symbol_id'   => $coinID,
                'symbol_type' => $coinType,
                'open'        => $open,
                'high'        => $targetHigh,
                'low'         => $targetLow,
                'close'       => $close,
                'sigma'       => $sigma,
                'start_at'    => Carbon::parse($startTime, config('app.timezone'))->setTimezone('UTC')->toDateTimeString(),
                'end_at'      => Carbon::parse($endTime, config('app.timezone'))->setTimezone('UTC')->toDateTimeString(),
                'status'      => CommonEnums::Yes,
                'creator'     => $uid,
            ];
            
            // 尝试保存机器人任务，如果失败则回滚事务并记录日志
            $task = ModelsBotTask::create($task);
            if (!$task) {
                DB::rollBack();
                Log::error('Create Bot Task Failed');
                throw new Exception('Failed');
            }
            
            $everySecondPrice = [];
            foreach ($data as $item) {
                $everySecondPrice[$item['timestamp'] / 1000] = $item['close'];
            }
            // 如果没有数据，则返回错误响应
            if (empty($everySecondPrice)) {
                Log::error('No Data');
                throw new Exception('Create Failed');
            }
            
            
            $queueKey = sprintf(config('kline.queue_key'), $symbol);
            // 尝试将数据缓存到队列中，如果失败则回滚事务并记录日志
            $result = RedisMarket()->set($queueKey, json_encode($everySecondPrice));
            if (!$result) {
                DB::rollBack();
                Log::error('Add Bot Task Failed');
                throw new Exception('Failed');
            }
            
            $this->newTask($task);
            // 删除原始缓存数据，提交事务，并返回成功响应
            DB::commit();
        } catch (Throwable $e) {
            // 捕获异常，回滚事务，并记录错误日志
            DB::rollBack();
            Log::error('Create Bot Task Failed:' . $e->getMessage());
            return $e->getMessage();
        }
    }
    
    public function generateHistoryData(
        string  $symbol,
        float   $startOpen,
        float   $targetHigh,
        float   $targetLow,
        float   $endClose,
        string  $startTime,
        string  $endTime,
        ?float  $sigma = 0.0003,
        ?int    $scale = 5,
        ?string $unit = '1m'
    ): array
    {
        set_time_limit(0);
        // 按 YYYY-mm-dd H:i:s 生成时间数据
        $days = $this->calcDays($startTime, $endTime);
        // 如果时间仅当天，则直接生成数据
        if (is_int($days)) {
            return GbmPathService::generateCandles(
                startOpen: $startOpen,
                endClose: $endClose,
                startTime: $startTime,
                endTime: $endTime,
                targetHigh: $targetHigh,
                targetLow: $targetLow,
                sigma: $sigma,
                scale: $scale
            );
        }
        $maxStep = count($days) - 1;
        // 生成价格
        $prices = GbmPathService::generateCandles(
            startOpen: $startOpen,
            endClose: $endClose,
            startTime: $startTime,
            endTime: $endTime,
            targetHigh: $targetHigh,
            targetLow: $targetLow,
            sigma: $sigma,
            intervalSeconds: 86400,
            scale: $scale,
            getPrices: true,
            maxStep: $maxStep
        );
        // 按天生成每秒价格
        for ($i = 0; $i < count($prices) - 1; $i++) {
            $open  = $prices[$i];
            $close = $prices[$i + 1];
            $high  = $open < $endClose ? $open + ($endClose - $open) / 2 : $open + ($targetHigh - $open) / 2;
            $high  = max($high, $close);
            $low   = $close < $endClose ? $close - ($endClose - $close) / 2 : $close - ($close - $endClose) / 2;
            if ($low < $targetLow) {
                $low = $targetLow;
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
                sigma: $sigma,
                scale: $scale,
                short: true
            );
            $minutes = $this->aggregates($kline, [$unit]);
            (new InfluxDB('market_spot'))->writeData($symbol, $unit, $minutes[$unit]);
        }
        return [];
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
                    $kline['c'] = (float)$row['c'];          // close 为该桶内最后一笔
                    $kline['v'] += $row['v'];
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
}
