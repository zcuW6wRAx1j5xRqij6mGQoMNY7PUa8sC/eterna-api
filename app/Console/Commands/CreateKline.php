<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

class CreateKline extends Command
{
    protected $signature = 'kline:aggregate
        {symbol : 交易对，例如 macusdt}
        {src=1m : 源周期，默认 1m}
        {targets=5m,15m,30m,1h,1d : 目标周期，逗号分隔}
        {--db=4 : Redis DB 索引}
        {--batch=5000 : 每批读取的条数}
        {--truncate : 聚合前清空目标 key}
        {--source-key= : 自定义源 key（默认 symbol:src）}
    ';
    
    protected $description = '废弃，请使用 kline:new';
    
    public function handle(): int
    {
        $symbol    = strtolower($this->argument('symbol'));
        $src       = strtolower($this->argument('src'));             // 预期 1m
        $targetsIn = strtolower($this->argument('targets'));
        $targets   = array_values(array_filter(array_map('trim', explode(',', $targetsIn))));
        $dbIndex   = (int)$this->option('db');
        $batchSize = (int)$this->option('batch');
        $truncate  = (bool)$this->option('truncate');
        $sourceKey = $this->option('source-key') ?: "{$symbol}:{$src}";
        
        // 周期 -> 毫秒
        $intervalMs = [
            '1m'  => 60_000,
            '5m'  => 5 * 60_000,
            '15m' => 15 * 60_000,
            '30m' => 30 * 60_000,
            '1h'  => 60 * 60_000,
            '1d'  => 24 * 60 * 60_000,
            '1w'  => 7 * 24 * 60 * 60_000,
            '1mth'=> null, // 占位：如需自然月，请单独实现
        ];
        
        // 过滤掉未实现的（月线等）
        $targets = array_values(array_filter($targets, fn($t) => isset($intervalMs[$t]) && $intervalMs[$t] !== null));
        if (empty($targets)) {
            $this->error('没有可聚合的目标周期（可用：5m,15m,30m,1h,1d,1w）。');
            return 1;
        }
        
        $this->info("源 key: {$sourceKey}，目标周期: " . implode(', ', $targets));
        $this->info("Redis DB: {$dbIndex}，batch: {$batchSize}" . ($truncate ? '，将清空目标 key' : ''));
        
        // 连接 & 选择DB
        $redis = Redis::connection();
        if (!$redis) {
            $this->error('Redis 连接失败');
            return 1;
        }
        $redis->select($dbIndex);
        
        // 开跑前对账：源总数
        try {
            $sourceCount = (int) $redis->zcard($sourceKey);
        } catch (Throwable $e) {
            $this->error("读取源 key 总数失败：{$e->getMessage()}");
            return 1;
        }
        $this->info("源 key {$sourceKey} 在 DB {$dbIndex} 的总条数：{$sourceCount}");
        
        // 目标 key
        $targetKeys = [];
        foreach ($targets as $t) {
            $targetKeys[$t] = "{$symbol}:{$t}";
        }
        
        // 如指定 truncate，先清空目标 key
        if ($truncate) {
            foreach ($targetKeys as $tKey) {
                try {
                    $redis->del($tKey);
                } catch (Throwable $e) {
                    $this->warn("清空 {$tKey} 失败：{$e->getMessage()}");
                }
            }
        }
        
        // 聚合缓冲：周期 => [bucketStartMs => aggRow]
        $buffers = [];
        foreach ($targets as $t) {
            $buffers[$t] = [];
        }
        
        $totalRead = 0;
        $totalOut  = array_fill_keys($targets, 0);
        
        // 刷写门槛
        $flushThreshold = 5000;
        
        // 扫描游标（score 下界）
        $lastScore = '-inf';
        
        try {
            while (true) {
                $options = [
                    'withscores' => true,
                    'limit'      => [0, $batchSize],
                ];
                // 首批 -inf；之后用 (lastScore) 严格排他
                $min = ($lastScore === '-inf') ? '-inf' : '(' . (string)((int)$lastScore);
                
                /** @var array<string,int|float|string> $batch  member => score */
                $batch = $redis->zrangebyscore($sourceKey, $min, '+inf', $options);
                
                if (empty($batch)) {
                    // 兜底：检查是否真的读完
                    $remaining = (int) $redis->zcount($sourceKey, $min, '+inf');
                    if ($remaining > 0 && $lastScore !== '-inf') {
                        // 可能因为边界格式导致解析失败，+1ms 再试
                        $this->warn("遇到空批但剩余还有 {$remaining} 条，尝试 lastScore+1 继续扫描…");
                        $lastScore = ((int)$lastScore) + 1;
                        continue;
                    }
                    // 刷写剩余缓冲并结束
                    $this->flushBuffers($redis, $targetKeys, $buffers, $totalOut);
                    break;
                }
                
                $countThisBatch = count($batch);
                $totalRead += $countThisBatch;
                
                foreach ($batch as $memberJson => $scoreMs) {
                    // 强制把 score 变成纯整数毫秒，避免科学计数法/小数点问题
                    $lastScore = (int)$scoreMs;
                    
                    $row = $this->decodeRow($memberJson);
                    if ($row === null) {
                        continue;
                    }
                    
                    $ts = $this->extractTsMs($row, $lastScore);
                    $ohlcv = $this->extractOhlcv($row);
                    if ($ohlcv === null) {
                        continue;
                    }
                    
                    foreach ($targets as $t) {
                        $ms = $intervalMs[$t];
                        $bucketStart = intdiv($ts, $ms) * $ms;
                        
                        if (!isset($buffers[$t][$bucketStart])) {
                            $buffers[$t][$bucketStart] = [
                                'tl' => $bucketStart,
                                'o'  => $ohlcv['o'],
                                'h'  => $ohlcv['h'],
                                'l'  => $ohlcv['l'],
                                'c'  => $ohlcv['c'],
                                'v'  => $ohlcv['v'],
                            ];
                        } else {
                            $b = &$buffers[$t][$bucketStart];
                            $b['h'] = max($b['h'], $ohlcv['h']);
                            $b['l'] = min($b['l'], $ohlcv['l']);
                            $b['c'] = $ohlcv['c'];      // 收盘为最新
                            $b['v'] += $ohlcv['v'];     // 量累加
                            unset($b);
                        }
                    }
                }
                
                // 缓冲达到阈值则刷写
                foreach ($targets as $t) {
                    if (count($buffers[$t]) >= $flushThreshold) {
                        $this->flushOne($redis, $targetKeys[$t], $buffers[$t], $totalOut[$t]);
                        $buffers[$t] = [];
                    }
                }
                
                $this->line("已读取：{$totalRead} 条…");
            }
        } catch (Throwable $e) {
            $this->error("运行失败：{$e->getMessage()}");
            // 最后尽力写出已有缓冲
            try {
                $this->flushBuffers($redis, $targetKeys, $buffers, $totalOut);
            } catch (Throwable $e2) {
                $this->warn("尝试刷写缓冲失败：{$e2->getMessage()}");
            }
            return 1;
        }
        
        $this->info(
            "完成。总读取 {$totalRead} 条；写出：" .
            collect($totalOut)->map(fn($v, $k) => "{$k}={$v}")->implode(', ')
        );
        
        return 0;
    }
    
    /**
     * 刷写所有周期缓冲
     */
    private function flushBuffers($redis, array $targetKeys, array &$buffers, array &$totalOut): void
    {
        foreach ($buffers as $t => $bucketMap) {
            if (!empty($bucketMap)) {
                $this->flushOne($redis, $targetKeys[$t], $bucketMap, $totalOut[$t]);
                $buffers[$t] = [];
            }
        }
    }
    
    /**
     * 将一个周期的聚合桶写回 Redis（score=tl, member=JSON）
     * 使用 PhpRedis/Predis 都兼容的三参 zadd。
     */
    private function flushOne($redis, string $targetKey, array $bucketMap, int &$counter): void
    {
        if (empty($bucketMap)) return;
        
        ksort($bucketMap, SORT_NUMERIC);
        
        $redis->pipeline(function ($pipe) use ($targetKey, $bucketMap, &$counter) {
            foreach ($bucketMap as $bucketStart => $row) {
                $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                // PhpRedis 兼容写法：key, score, member
                $pipe->zadd($targetKey, (int)$bucketStart, $json);
                $counter++;
            }
        });
    }
    
    /**
     * 安全解码 JSON 成数组
     */
    private function decodeRow(string $json): ?array
    {
        try {
            $row = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }
    
    /**
     * 提取毫秒级时间戳。优先从成员字段取，否则用 score。
     * 兼容秒/毫秒。
     */
    private function extractTsMs(array $row, int $fallbackScoreMs): int
    {
        foreach (['tl','ts','t','time','timestamp'] as $k) {
            if (isset($row[$k])) {
                $v = $row[$k];
                if ($v >= 1_000_000_000_000) return (int)$v;          // ms
                if ($v >= 1_000_000_000)    return (int)$v * 1000;    // sec -> ms
            }
        }
        return (int)$fallbackScoreMs;
    }
    
    /**
     * 提取 OHLCV（没有 volume 时置 0）
     * 兼容 o/h/l/c/v 与 open/high/low/close/volume 命名
     */
    private function extractOhlcv(array $row): ?array
    {
        $get = function(array $c, array $keys, $default=null) {
            foreach ($keys as $k) if (array_key_exists($k, $c)) return $c[$k];
            return $default;
        };
        
        $o = $get($row, ['o','open']);
        $h = $get($row, ['h','high']);
        $l = $get($row, ['l','low']);
        $c = $get($row, ['c','close']);
        $v = (float)($get($row, ['v','volume'], 0) ?? 0);
        
        if ($o === null || $h === null || $l === null || $c === null) {
            return null;
        }
        
        return [
            'o' => (float)$o,
            'h' => (float)$h,
            'l' => (float)$l,
            'c' => (float)$c,
            'v' => $v,
        ];
    }
}
