<?php

declare(strict_types=1);

namespace App\Internal\Tools\Services;

use DateTimeInterface;
use Carbon\CarbonImmutable;
use Random\RandomException;
use InvalidArgumentException;
use Illuminate\Support\Carbon;

final class KlineService {
    public static function generateCandles(
        float                    $startOpen,
        float                    $endClose,
        string|DateTimeInterface $startTime,
        string|DateTimeInterface $endTime,
        float                    $targetHigh,
        float                    $targetLow,
        int                      $intervalMinutes = 1,
        float                    $sigma = 0.001,
        ?int                     $scale = 5,
    ): array
    {
        // ---------- 校验 ----------
//        if (!is_finite($startOpen) || !is_finite($endClose) || !is_finite($targetHigh) || !is_finite($targetLow)) {
//            throw new InvalidArgumentException('价格参数必须为有限数值');
//        }
//        if ($targetHigh <= $targetLow) {
//            throw new InvalidArgumentException('targetHigh 必须大于 targetLow');
//        }
//        if ($startOpen < $targetLow || $startOpen > $targetHigh) {
//            throw new InvalidArgumentException('startOpen 必须处于 [targetLow, targetHigh] 范围内');
//        }
//        if ($endClose < $targetLow || $endClose > $targetHigh) {
//            throw new InvalidArgumentException('endClose 必须处于 [targetLow, targetHigh] 范围内');
//        }
        
        $start = Carbon::parse($startTime, config('app.timezone'));
        
        $end = Carbon::parse($endTime, config('app.timezone'));
        
        $totalMinutes = max(0, (int)$end->diffInSeconds($start));
        $steps        = max(3, intdiv(max(1, $totalMinutes), max(1, $intervalMinutes))); // 至少 3 根
        
        // 三段：start→High（触及高）；High→Low（触及低）；Low→end（不再触及边界）
        $seg1 = max(1, intdiv($steps, 3));
        $seg2 = max(1, intdiv($steps, 3));
        $seg3 = max(1, $steps - $seg1 - $seg2);
        
        $eps = max(1e-8, (abs($targetHigh) + abs($targetLow)) * 1e-9); // 开区间边距
        
        $prices   = [];
        $prices[] = $startOpen;
        
        // 段1：单调上行到 targetHigh，最后一点==targetHigh，其余 < targetHigh
        $prices = array_merge($prices, self::monotoneSegment(
            start: $startOpen,
            end: $targetHigh,
            steps: $seg1,
            lowBound: $targetLow + $eps,
            highBound: $targetHigh,
            touchEndExactly: true,     // 在段尾精确触达高点
            keepInteriorOpen: true     // 内部严格小于 high
        ));
        
        // 段2：单调下行到 targetLow，最后一点==targetLow，其余 > targetLow
        $prices = array_merge($prices, self::monotoneSegment(
            start: $targetHigh,
            end: $targetLow,
            steps: $seg2,
            lowBound: $targetLow,
            highBound: $targetHigh - $eps,
            touchEndExactly: true,     // 在段尾精确触达低点
            keepInteriorOpen: true     // 内部严格大于 low
        ));
        
        // 段3：从低点到终点，保持在开区间 (low, high)，不再触及边界
        $endTarget = self::clampOpen($endClose, $targetLow, $targetHigh, $eps);
        $prices    = array_merge($prices, self::monotoneSegment(
            start: $targetLow,
            end: $endTarget,
            steps: $seg3,
            lowBound: $targetLow + $eps,
            highBound: $targetHigh - $eps,
            touchEndExactly: true,     // 精确到指定收盘
            keepInteriorOpen: true
        ));
        
        // ----------- 转蜡烛（影线不越界；极值蜡烛只触达一次） -----------
        $candles = [];
        $t       = $start;
        $n       = count($prices) - 1;
        
        // 段间边界索引（便于识别触顶/触底蜡烛）
        $idxTouchHigh = $seg1;                                         // 段1结束处的 close==targetHigh
        $idxTouchLow  = $seg1 + $seg2;                                 // 段2结束处的 close==targetLow
        
        for ($i = 0; $i < $n; $i++) {
            $open     = $prices[$i];
            $close    = $prices[$i + 1];
            $baseHigh = max($open, $close);
            $baseLow  = min($open, $close);
            
            // 影线轻微扰动，但严格不越界，且极值蜡烛不再增/减影线
            $high = $baseHigh;
            $low  = $baseLow;
            
            if ($i !== $idxTouchHigh - 1 && $i + 1 !== $idxTouchHigh) {
                // 普通蜡烛：上影不超过 targetHigh - eps
                $high = min($targetHigh - $eps, $baseHigh + (mt_rand(0, 5) / 1000.0) * $baseHigh);
            } else {
                // 触顶蜡烛：确保最高价==targetHigh
                $high = $targetHigh;
            }
            
            if ($i !== $idxTouchLow - 1 && $i + 1 !== $idxTouchLow) {
                // 普通蜡烛：下影不低于 targetLow + eps
                $low = max($targetLow + $eps, $baseLow - (mt_rand(0, 5) / 1000.0) * $baseLow);
            } else {
                // 触底蜡烛：确保最低价==targetLow
                $low = $targetLow;
            }
            
            // 再次保证区间
            $high = min($high, $targetHigh);
            $low  = max($low, $targetLow);
            if ($low > $high) {
                $low = $high;
            }
            
            $candles[] = [
                'open'      => round($open, $scale),
                'high'      => round($high, $scale),
                'low'       => round($low, $scale),
                'close'     => round($close, $scale),
                'timestamp' => $t->copy()->timestamp * 1000,
            ];
            
            $t = $t->addSeconds($intervalMinutes);
        }
        
        return $candles;
    }
    
    /**
     * 生成“单调”段：将总变化量拆成随机正份额，保证：
     * - 若向上：内部点 < end（可选开区间限制），尾点==end（若 touchEndExactly）
     * - 若向下：内部点 > end（可选开区间限制），尾点==end（若 touchEndExactly）
     * - 全程限制在 [lowBound, highBound]（内部可要求严格开区间）
     *
     * 返回的是该段的“后续价格点”数组（长度 == steps），首点不含 start，末点为 end（若 touchEndExactly）
     */
    private static function monotoneSegment(
        float $start,
        float $end,
        int   $steps,
        float $lowBound,
        float $highBound,
        bool  $touchEndExactly = true,
        bool  $keepInteriorOpen = true
    ): array
    {
        $steps = max(1, $steps);
        $up    = ($end >= $start);
        $delta = $end - $start;
        
        // 生成正权重并归一化（类似 Dirichlet），形成单调路径
        $weights = [];
        $sum     = 0.0;
        for ($i = 0; $i < $steps; $i++) {
            // 指数分布样的随机份额，避免均匀死板
            $w         = -\log(max(1e-12, mt_rand() / mt_getrandmax()));
            $weights[] = $w;
            $sum       += $w;
        }
        // 防止极端：拉平一点
        $avg = $sum / $steps;
        for ($i = 0; $i < $steps; $i++) {
            $weights[$i] = 0.5 * $weights[$i] + 0.5 * $avg;
        }
        $sum = array_sum($weights);
        
        $path = [];
        $curr = $start;
        for ($i = 0; $i < $steps; $i++) {
            // 最后一步强制精确到 end（若要求）
            if ($touchEndExactly && $i === $steps - 1) {
                $curr = $end;
            } else {
                $inc  = $delta * ($weights[$i] / $sum); // 同号增量
                $curr += $inc;
                
                // 边界处理（内部点保持开区间时，略微回退）
                if ($keepInteriorOpen) {
                    $eps  = max(1e-8, (abs($highBound) + abs($lowBound)) * 1e-9);
                    $curr = min($curr, $highBound - $eps);
                    $curr = max($curr, $lowBound + $eps);
                } else {
                    $curr = min($curr, $highBound);
                    $curr = max($curr, $lowBound);
                }
                
                // 单调性兜底（浮点偶发抖动）
                if ($up) {
                    $curr = max($curr, $path[$i - 1] ?? $start);
                } else {
                    $curr = min($curr, $path[$i - 1] ?? $start);
                }
            }
            $path[] = $curr;
        }
        return $path;
    }
    
    /** 将值收紧到开区间 (low, high)，保留与目标的距离方向 */
    private static function clampOpen(float $v, float $low, float $high, float $eps): float
    {
        $v = min($v, $high - $eps);
        $v = max($v, $low + $eps);
        return $v;
    }
}
