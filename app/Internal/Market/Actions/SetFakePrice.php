<?php

namespace Internal\Market\Actions;

use App\Enums\CommonEnums;
use App\Enums\OrderEnums;
use App\Enums\SymbolEnums;
use App\Models\PlatformSymbolPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/** @package Internal\Market\Actions */
class SetFakePrice {

    public function __invoke(PlatformSymbolPrice $platformSymbolPrice, $after)
    {
        $task = PlatformSymbolPrice::find($platformSymbolPrice->id);
        if ($task->task_id != $platformSymbolPrice->task_id) {
            Log::error('控盘失败, 任务ID不匹配', [
                'taskId' => $platformSymbolPrice->id,
                'taskId2' => $task->task_id,
            ]);
            return true;
        }

        $key = sprintf(OrderEnums::SpotFakePriceKey, $task->symbol->symbol) ;
        // $key = $task->symbol_type == SymbolEnums::SymbolTypeSpot ? sprintf(OrderEnums::SpotFakePriceKey, $task->symbol->symbol) : sprintf(OrderEnums::FuturesFakePriceKey, $task->symbol->symbol) ;

        $payload = [
            'price'=>$task->fake_price,
            // 'duration'=>$task->duration_time,
            'start_time'=>Carbon::now()->addMinutes($after)->unix(),
        ];
        Log::info('控盘成功', [$payload]);
        // 多2秒冗余时间
        // $jobStart = Carbon::now()->addMinutes($after);
        // $jobStopTime = $jobStart->copy()->addMinutes(1)->minute;

        // php 不对key 做过期处理, go程序处理完负责处理key
        $res = RedisMarket()->set($key, json_encode($payload));
        Log::info('---',['result'=>$res,'key'=>$key]);
        return true;
    }

    /**
     * 手动取消控盘
     * @param int $taskId 
     * @return true 
     */
    public function handleCancel(int $taskId) {
        $task = PlatformSymbolPrice::find($taskId);
        if (!$task) {
            Log::error('取消控盘失败, 任务不存在', ['taskId' => $taskId]);
            return true;
        }

        $task->status = CommonEnums::No;
        $task->start_time = null;
        $task->duration_time = 0;
        $task->fake_price = 0;
        $task->task_id = '';
        $task->save();
        
        $key = sprintf(OrderEnums::SpotFakePriceKey, $task->symbol->symbol);
        // $key = $task->symbol_type == SymbolEnums::SymbolTypeSpot ? sprintf(OrderEnums::SpotFakePriceKey, $task->symbol->symbol) : sprintf(OrderEnums::FuturesFakePriceKey, $task->symbol->symbol) ;
        RedisMarket()->delete($key);
        return true;
    }

    /**
     * 定时任务取消控盘
     * @param PlatformSymbolPrice $platformSymbolPrice 
     * @return true 
     */
    public function jobCancel(PlatformSymbolPrice $platformSymbolPrice) {

        return;

        $task = PlatformSymbolPrice::find($platformSymbolPrice->id);
        if (!$task) {
            Log::error('取消控盘失败, 任务不存在', ['taskId' => $platformSymbolPrice->id]);
            return true;
        }

        if ($task->task_id != $platformSymbolPrice->task_id) {
            Log::warning('取消控盘失败, 任务ID不匹配', [
                'taskId' => $platformSymbolPrice->id,
                'taskId2' => $task->task_id,
            ]);
            return true;
        }

        $this->handleCancel($platformSymbolPrice->id);
        return true;
    }
}
