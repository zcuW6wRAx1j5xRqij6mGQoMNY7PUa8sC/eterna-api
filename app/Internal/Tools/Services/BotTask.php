<?php

namespace App\Internal\Tools\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use App\Exceptions\LogicException;
use DateTime;
use DateTimeZone;
use Exception;
use Throwable;
use Carbon\Carbon;
use App\Models\Symbol;
use App\Enums\CommonEnums;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
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
        ?string $unit = '1m',
        ?int    $isDel = 0
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
        $prices  = GbmPathService::generateCandles(
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
        $service = new InfluxDB('market_spot');
        if ($isDel) {
            $service->deleteData($symbol);
        }
        $minutes = [];
        $redis   = Redis::connection();
        // 按天生成每秒价格
        for ($i = 0; $i < count($prices) - 1; $i++) {
            $open       = $prices[$i];
            $close      = $prices[$i + 1];
            $maxOffset  = rand(0, (int)((($endClose - $open) / 2) * 10000)) / 10000;
            $maxOffsets = rand(0, (int)((($targetHigh - $open) / 2) * 10000)) / 10000;
            $high       = $open < $endClose ? $open + abs($maxOffset) : $open + abs($maxOffsets);
            $high       = max($high, $close);
            $minOffset  = rand(0, (int)((($endClose - $close) / 2) * 10000)) / 10000;
            $minOffset2 = rand(0, (int)((($close - $endClose) / 2) * 10000)) / 10000;
            $low        = $close < $endClose ? $close - abs($minOffset) : $close - abs($minOffset2);
            if ($low < $targetLow) {
                $low = $targetLow;
            } else if ($low > $open) {
                $low = $open;
            }
            
            $kline = GbmPathService::generateCandles(
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
            $data  = $this->aggregates($kline, [$unit]);
//            $service->writeData($symbol, $unit, $data[$unit]);
//            $minutes = array_merge($minutes, $data[$unit]);
            $minutes = $data[$unit];
            // 使用 redis 管道批量写入数据库
            $redis->pipeline(function ($pipe) use ($symbol, $minutes, $unit) {
                foreach ($minutes as $minute) {
                    $pipe->zadd($symbol . ":" . $unit, $minute['tl'], json_encode($minute));
                }
            });
        }
        $all = $this->aggregates($minutes, ['5m', '15m', '30m', '1d']);
//        $service->writeData($symbol, '5m', $all['5m']);
//        Log::info("聚合数据5分钟：", $all['5m']);
//        $service->writeData($symbol, '15m', $all['15m']);
//        Log::info("聚合数据15分钟：", $all['15m']);
//        $service->writeData($symbol, '30m', $all['30m']);
//        Log::info("聚合数据30分钟：", $all['30m']);
//        $service->writeData($symbol, '1d', $all['1d']);
//        Log::info("聚合数据天：", $all['1d']);
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
    
    
    public function generateWeeklyAndMonthlyKLines($klines)
    {
        // 按时间戳排序（确保顺序正确）
        usort($klines, function ($a, $b) {
            return $a['tl'] <=> $b['tl'];
        });
        
        $weekly  = [];
        $monthly = [];
        
        foreach ($klines as $k) {
            $ts_sec = $k['tl'] / 1000;
            $dt     = new DateTime("@$ts_sec");
            $dt->setTimezone(new DateTimeZone('UTC')); // 建议统一用 UTC
            
            // === 1. 自然周（周一为起始）===
            $week_start = clone $dt;
            $weekday    = (int)$week_start->format('N'); // 1=Mon, 7=Sun
            $week_start->modify("-" . ($weekday - 1) . " days");
            $week_start->setTime(0, 0, 0);
            $week_key = $week_start->format('Y-\W%W'); // 如 2024-W36
            
            $week_end = clone $week_start;
            $week_end->modify('+6 days')->setTime(23, 59, 59);
            
            // === 2. 自然月 ===
            $month_key = $dt->format('Y-m');           // 2024-09
            $month_end = clone $dt;
            $month_end->modify('last day of this month')->setTime(23, 59, 59);
            
            // === 聚合周K ===
            if (!isset($weekly[$week_key])) {
                $weekly[$week_key] = [
                    'o'        => $k['o'],
                    'h'        => $k['h'],
                    'l'        => $k['l'],
                    'c'        => $k['c'],
                    'v'        => $k['v'],
                    'tl_start' => $k['tl'],
                    'tl'       => $week_end->getTimestamp() * 1000, // 毫秒
                ];
            } else {
                $weekly[$week_key]['h'] = max($weekly[$week_key]['h'], $k['h']);
                $weekly[$week_key]['l'] = min($weekly[$week_key]['l'], $k['l']);
                $weekly[$week_key]['c'] = $k['c'];
                $weekly[$week_key]['v'] += $k['v'];
            }
            
            // === 聚合月K ===
            if (!isset($monthly[$month_key])) {
                $monthly[$month_key] = [
                    'o'        => $k['o'],
                    'h'        => $k['h'],
                    'l'        => $k['l'],
                    'c'        => $k['c'],
                    'v'        => $k['v'],
                    'tl_start' => $k['tl'],
                    'tl'       => $month_end->getTimestamp() * 1000,
                ];
            } else {
                $monthly[$month_key]['h'] = max($monthly[$month_key]['h'], $k['h']);
                $monthly[$month_key]['l'] = min($monthly[$month_key]['l'], $k['l']);
                $monthly[$month_key]['c'] = $k['c'];
                $monthly[$month_key]['v'] += $k['v'];
            }
        }
        
        // 转为有序数组
        $result_weekly = array_values($weekly);
        usort($result_weekly, fn($a, $b) => $a['tl_start'] <=> $b['tl_start']);
        foreach ($result_weekly as $k => $v) {
            unset($result_weekly[$k]['tl_start']);
        }
        
        $result_monthly = array_values($monthly);
        usort($result_monthly, fn($a, $b) => $a['tl_start'] <=> $b['tl_start']);
        foreach ($result_monthly as $k => $v) {
            unset($result_monthly[$k]['tl_start']);
        }
        
        return [
            'weekly'  => $result_weekly,
            'monthly' => $result_monthly,
        ];
    }
    
    public function createKline($symbol, $unit, $internal)
    {
        $key    = $symbol . ':1m';
        $redis  = Redis::connection();
        $length = $redis->zcount($key, '-inf', '+inf');
        $influx = new InfluxDB('market_spot');
        for ($i = 0; $i < $length; $i += $unit) {
            $data = $redis->zrange($key, $i, $i + ($unit - 1));
            $data = array_map(function ($item) {
                return json_decode($item, true);
            }, $data);
            $data = $this->aggregates($data, [$internal]);
            $data = $data[$internal][0];
//            $redis->zadd($symbol . ':' . $internal, $data['tl'], json_encode($data));
            $influx->writeData($symbol, $internal, $data);
            $data = [];
        }
    }
    
}
