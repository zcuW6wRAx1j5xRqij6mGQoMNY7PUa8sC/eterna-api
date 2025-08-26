<?php

namespace App\Internal\Tools\Services;

use App\Exceptions\LogicException;
use Exception;
use Throwable;
use Carbon\Carbon;
use App\Models\Symbol;
use App\Enums\CommonEnums;
use Illuminate\Support\Facades\Cache;
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
        float  $startOpen,
        float  $targetHigh,
        float  $targetLow,
        float  $endClose,
        string $startTime,
        string $endTime,
        ?float $sigma = 0.0003,
        ?int   $scale = 5
    ): array
    {
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
            $kline = GbmPathService::generateCandles(
                startOpen: $open,
                endClose: $close,
                startTime: $days[$i],
                endTime: $days[$i + 1],
                targetHigh: $high,
                targetLow: $low,
                sigma: $sigma,
                microSeconds: false
            );
            dd($kline);
            /**
             * getPrice=true
             * array:61 [▼ // app/Internal/Tools/Services/BotTask.php:257
             * 0 => "50.00000"
             * 1 => "50.01109"
             * 2 => "50.00010"
             * 3 => "50.00232"
             * 4 => "50.03116"
             * 5 => "50.01592"
             * 6 => "50.01170"
             * 7 => "50.00043"
             */
            /**
             * getPrice=false
             *array:60 [▼ // app/Internal/Tools/Services/BotTask.php:256
             * 0 => array:5 [▼
             * "open" => "50.00000"
             * "high" => "51.31192"
             * "low" => "50.00000"
             * "close" => "51.31192"
             * "timestamp" => 1756223940
             * ]
             * 1 => array:5 [▼
             * "open" => "51.31192"
             * "high" => "52.62956"
             * "low" => "51.31192"
             * "close" => "52.62956"
             * "timestamp" => 1756223941
             * ]
             * ]
             */
            //todo 处理 Kline 数据
        }
        return $prices;
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
}
