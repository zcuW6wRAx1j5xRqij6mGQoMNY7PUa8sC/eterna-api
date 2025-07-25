<?php

namespace App\Internal\Tools\Services;

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



    public function __invoke()
    {

    }

    public function changeFloat($symbol, $bound) {
        RedisMarket()->publish(self::TaskCommandQueueName,json_encode([
            'type'=>self::TaskCommandTypeFreeFloat,
            'symbol'=>strtoupper($symbol),
            'bound'=>$bound,
        ]));
        return true;
    }

    public function newTask(ModelsBotTask $task) {
        RedisMarket()->publish(self::TaskCommandQueueName,json_encode([
            'type'=>self::TaskCommandTypeNewTask,
//            'id'=>(string)$task->id,
            'symbol'=>strtoupper($task->symbol->symbol),
//            'start'=>$task->start_at,
//            'end'=>$task->end_at,
//            'high'=>(float)$task->high,
//            'low'=>(float)$task->low,
//            'close'=>(float)$task->close
        ]));
        return true;
    }

    public function stopTask(ModelsBotTask $task) {
        $symbol = strtoupper($task->symbol->symbol);

        $start  = strtotime($task->start_at);
        $end    = strtotime($task->end_at)+1;

        $queueKey  = sprintf(config('kline.queue_key'), $symbol);
        $cachedata = RedisMarket()->get($queueKey);
        $cachedata = $cachedata?json_decode($cachedata, true):[];
        $cachedata = $cachedata?:[];

        if($cachedata){
            for ($timestamp = $start; $timestamp < $end; $timestamp++) {
                if(array_key_exists($timestamp, $cachedata)){
                    unset($cachedata[$timestamp]);
                }
            }
        }
        if(count($cachedata)>0){
            RedisMarket()->set($queueKey, json_encode($cachedata));
        }else{
            RedisMarket()->delete($queueKey);
        }
        RedisMarket()->publish(self::TaskCommandQueueName,json_encode([
            'type'=>self::TaskCommandTypeStopTask,
            'id'=>(string)$task->id,
            'symbol'=>$symbol,
        ]));
        return true;
    }

}
