<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

class CreateMonthKline extends Command
{
    protected $signature = 'kline:new
        {symbol : 交易对，例如 macusdt}
        {src=1m : 源周期，默认 1m}
        {targets=5m,15m,30m,1h,1d,1M : 目标周期，逗号分隔（支持 1M 自然月）}
        {--db=4 : Redis DB 索引}
        {--batch=5000 : 每批读取的条数}
        {--truncate : 聚合前清空目标 key}
        {--source-key= : 自定义源 key（默认 symbol:src）}
        {--tz=UTC : 聚合对齐用的时区，如 Asia/Bangkok 或 UTC}
    ';
    
    protected $description = '从 Redis ZSET 的 1m 数据聚合生成更大周期（含自然月 1M），写回 Redis ZSET。';
    
    public function handle(): int
    {
        $symbol    = strtolower($this->argument('symbol'));
        $src       = strtolower($this->argument('src'));                      // 预期 1m
        $targetsIn = $this->argument('targets');                               // 保留原大小写，后续规范化
        $targets   = $this->normalizeTargets($targetsIn);                      // 见下方方法
        $dbIndex   = (int)$this->option('db');
        $batchSize = (int)$this->option('batch');
        $truncate  = (bool)$this->option('truncate');
        $tzName    = $this->option('tz') ?: 'UTC';
        $sourceKey = $this->option('source-key') ?: "{$symbol}:{$src}";
        
        // 固定周期（毫秒步长对齐）
        $fixedIntervalsMs = [
            '1m'  => 60_000,
            '5m'  => 5 * 60_000,
            '15m' => 15 * 60_000,
            '30m' => 30 * 60_000,
            '1h'  => 60 * 60_000,
            '1d'  => 24 * 60 * 60_000,
            '1w'  => 7 * 24 * 60 * 60_000,
        ];
        // 日历周期（自然月）
        $calendarIntervals = ['1M'];
        
        // 仅保留实现的周期
        $targets = array_values(array_filter(
            $targets,
            fn($t) => isset($fixedIntervalsMs[$t]) || in_array($t, $calendarIntervals, true)
        ));
        if (empty($targets)) {
            $this->error('没有可聚合的目标周期（支持：5m,15m,30m,1h,1d,1w,1M）。');
            return 1;
        }
        
        $this->info("源 key: {$sourceKey}，目标周期: " . implode(', ', $targets));
        $this->info("Redis DB: {$dbIndex}，batch: {$batchSize}" . ($truncate ? '，将清空目标 key' : ''));
        $this->info("对齐时区: {$tzName}");
        
        // 连接 & 选择 DB
        $redis = Redis::connection();
        if (!$redis) {
            $this->error('Redis 连接失败');
            return 1;
        }
        $redis->select($dbIndex);
        
        // 源总数对账
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
        
        // 可选清空目标
        if ($truncate) {
            foreach ($targetKeys as $tKey) {
                try { $redis->del($tKey); } catch (Throwable $e) { $this->warn("清空 {$tKey} 失败：{$e->getMessage()}"); }
            }
        }
        
        // 聚合缓冲
        $buffers = [];
        foreach ($targets as $t) { $buffers[$t] = []; }
        
        $totalRead      = 0;
        $totalOut       = array_fill_keys($targets, 0);
        $flushThreshold = 5000;
        $lastScore      = '-inf';
        $tz             = new \DateTimeZone($tzName);
        
        try {
            while (true) {
                $options = ['withscores' => true, 'limit' => [0, $batchSize]];
                $min = ($lastScore === '-inf') ? '-inf' : '(' . (string)((int)$lastScore);
                
                /** @var array<string,int|float|string> $batch member => score */
                $batch = $redis->zrangebyscore($sourceKey, $min, '+inf', $options);
                
                if (empty($batch)) {
                    // 兜底：确认是否真的读完
                    $remaining = (int) $redis->zcount($sourceKey, $min, '+inf');
                    if ($remaining > 0 && $lastScore !== '-inf') {
                        $this->warn("遇到空批但剩余还有 {$remaining} 条，尝试 lastScore+1 继续扫描…");
                        $lastScore = ((int)$lastScore) + 1;
                        continue;
                    }
                    $this->flushBuffers($redis, $targetKeys, $buffers, $totalOut);
                    break;
                }
                
                $totalRead += count($batch);
                
                foreach ($batch as $memberJson => $scoreMs) {
                    $lastScore = (int)$scoreMs; // 强制整数毫秒，避免科学计数/小数点
                    
                    $row = $this->decodeRow($memberJson);
                    if ($row === null) continue;
                    
                    $ts  = $this->extractTsMs($row, $lastScore);
                    $ohl = $this->extractOhlcv($row);
                    if ($ohl === null) continue;
                    
                    foreach ($targets as $t) {
                        $bucketStart = $this->bucketStartMs($ts, $t, $fixedIntervalsMs, $tz);
                        if (!isset($buffers[$t][$bucketStart])) {
                            $buffers[$t][$bucketStart] = [
                                'tl' => $bucketStart,
                                'o'  => $ohl['o'],
                                'h'  => $ohl['h'],
                                'l'  => $ohl['l'],
                                'c'  => $ohl['c'],
                                'v'  => $ohl['v'],
                            ];
                        } else {
                            $b = &$buffers[$t][$bucketStart];
                            $b['h'] = max($b['h'], $ohl['h']);
                            $b['l'] = min($b['l'], $ohl['l']);
                            $b['c'] = $ohl['c'];
                            $b['v'] += $ohl['v'];
                            unset($b);
                        }
                    }
                }
                
                // 达阈值分周期刷写
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
            try { $this->flushBuffers($redis, $targetKeys, $buffers, $totalOut); }
            catch (Throwable $e2) { $this->warn("尝试刷写缓冲失败：{$e2->getMessage()}"); }
            return 1;
        }
        
        $this->info(
            "完成。总读取 {$totalRead} 条；写出：" .
            collect($totalOut)->map(fn($v,$k)=>"{$k}={$v}")->implode(', ')
        );
        
        return 0;
    }
    
    /**
     * 将逗号分隔的 targets 规范化：
     * - 固定周期统一为小写（5m/15m/30m/1h/1d/1w）
     * - 月线支持：1M（保持大写），或 1mo/1mon/1month（不区分大小写）=> 1M
     */
    private function normalizeTargets(string $targetsIn): array
    {
        $out = [];
        foreach (array_filter(array_map('trim', explode(',', $targetsIn))) as $raw) {
            if ($raw === '') continue;
            
            // 月线优先识别
            if (strcasecmp($raw, '1M') === 0) {
                $out[] = '1M';
                continue;
            }
            $l = strtolower($raw);
            if (in_array($l, ['1mo','1mon','1month'], true)) {
                $out[] = '1M';
                continue;
            }
            
            // 其他固定周期统一小写
            $out[] = $l;
        }
        // 去重并保持原顺序
        return array_values(array_unique($out));
    }
    
    /**
     * 计算桶起点（毫秒）：
     * - 固定周期：整数毫秒步长对齐
     * - 1M：按 tz 对齐到自然月月首 00:00:00，再转换到 UTC 毫秒
     */
    private function bucketStartMs(int $tsMs, string $interval, array $fixedIntervalsMs, \DateTimeZone $tz): int
    {
        if (isset($fixedIntervalsMs[$interval])) {
            $ms = $fixedIntervalsMs[$interval];
            return intdiv($tsMs, $ms) * $ms;
        }
        
        if ($interval === '1M') {
            // 先按业务时区解释时间，取所在月的月首 00:00:00
            $dtLocal = CarbonImmutable::createFromTimestampMs($tsMs, $tz)->startOfMonth();
            // 再转换到 UTC 以获得稳定的毫秒时间戳（写回 score 用 UTC 毫秒）
            $dtUtc = $dtLocal->setTimezone('UTC');
            return (int)$dtUtc->valueOf();
        }
        
        // fallback：按分钟
        return intdiv($tsMs, 60_000) * 60_000;
    }
    
    /** 刷写所有周期缓冲 */
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
     * 将一个周期的桶写回 Redis（score=tl, member=JSON）
     * 使用 PhpRedis/Predis 兼容的三参 zadd。
     */
    private function flushOne($redis, string $targetKey, array $bucketMap, int &$counter): void
    {
        if (empty($bucketMap)) return;
        
        ksort($bucketMap, SORT_NUMERIC);
        
        $redis->pipeline(function ($pipe) use ($targetKey, $bucketMap, &$counter) {
            foreach ($bucketMap as $bucketStart => $row) {
                $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $pipe->zadd($targetKey, (int)$bucketStart, $json);
                $counter++;
            }
        });
    }
    
    /** 安全解码 JSON */
    private function decodeRow(string $json): ?array
    {
        try {
            $row = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }
    
    /** 提取毫秒时间戳（优先成员字段，兼容秒/毫秒；否则用 score） */
    private function extractTsMs(array $row, int $fallbackScoreMs): int
    {
        foreach (['tl','ts','t','time','timestamp'] as $k) {
            if (isset($row[$k])) {
                $v = $row[$k];
                if ($v >= 1_000_000_000_000) return (int)$v;       // ms
                if ($v >= 1_000_000_000)    return (int)$v * 1000; // sec -> ms
            }
        }
        return (int)$fallbackScoreMs;
    }
    
    /** 提取 OHLCV（volume 缺省置 0），兼容多种命名 */
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
        
        if ($o === null || $h === null || $l === null || $c === null) return null;
        
        return [
            'o' => (float)$o,
            'h' => (float)$h,
            'l' => (float)$l,
            'c' => (float)$c,
            'v' => $v,
        ];
    }
}
