<?php
declare(strict_types=1);

namespace App\Internal\Tools\Services;

use DateTimeInterface;
use Carbon\CarbonImmutable;
use Random\RandomException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class GbmPathService {
    
    private static $open;
    private static $close;
    private static $targetHigh;
    private static $targetLow;
    
    /**
     * 生成模拟的K线数据（蜡烛图数据）
     *
     * 该函数根据给定的起始价格、目标最高价和最低价，使用几何布朗运动（GBM）生成一段模拟的价格路径，
     * 并将其划分为三个阶段：上涨到目标高点、下跌到目标低点、再上涨或下跌至结束收盘价。
     * 每个价格点之间的时间间隔由 $intervalSeconds 控制，波动程度由 $sigma 控制。
     *
     * @param float                    $startOpen       起始开盘价
     * @param float                    $endClose        结束收盘价
     * @param string|DateTimeInterface $startTime       开始时间，支持字符串或 DateTimeInterface
     * @param string|DateTimeInterface $endTime         结束时间，支持字符串或 DateTimeInterface
     * @param float                    $targetHigh      目标最高价
     * @param float                    $targetLow       目标最低价
     * @param int                      $intervalSeconds 每根K线的时间间隔（秒），默认为1秒钟
     * @param float                    $sigma           波动率参数，用于控制价格波动幅度，默认为0.02
     * @param ?int                     $scale           小数位数，默认为5
     *
     *
     * @return array 返回一个包含K线数据的数组，每根K线包含 open, high, low, close, volume, time 字段
     */
    public static function generateCandles(
        float                    $startOpen,
        float                    $endClose,
        string|DateTimeInterface $startTime,
        string|DateTimeInterface $endTime,
        float                    $targetHigh,
        float                    $targetLow,
        float                    $sigma = 0.001,
        int                      $intervalSeconds = 1,
        ?int                     $scale = 5,
    ): array
    {
        try {
            self::$open       = $startOpen;
            self::$close      = $endClose;
            self::$targetHigh = $targetHigh;
            self::$targetLow  = $targetLow;
            // 解析开始和结束时间
            $start = Carbon::parse($startTime, config('app.timezone'));
            
            $end = Carbon::parse($endTime, config('app.timezone'));
            // 计算总分钟数和总步数
            $totalMinutes = max(0, $end->diffInSeconds($start));
            $steps        = max(3, intdiv(max(1, $totalMinutes), max(1, $intervalSeconds)));
            
            // 将整个时间段划分为三个阶段
            $seg1 = max(1, intdiv($steps, 3));
            $seg2 = max(1, intdiv($steps, 3));
            $seg3 = max(1, $steps - $seg1 - $seg2);
            
            // 构造价格序列：起始价 + 三段GBM路径
            $prices = [$startOpen];

//            $lo = min($targetLow, $startOpen, $endClose);
//            $hi = max($targetHigh, $startOpen, $endClose);

//            $prices = array_merge($prices, self::rangeBoundSegment($startOpen,  $targetHigh, $seg1, $lo, $hi, $sigma, 3.0));
//            $prices = array_merge($prices, self::rangeBoundSegment($targetHigh, $targetLow,  $seg2, $lo, $hi, $sigma, 3.0));
//            $prices = array_merge($prices, self::rangeBoundSegment($targetLow,  $endClose,   $seg3, $lo, $hi, $sigma, 3.0));
            $upOrLow = rand(0, 1);
            if ($upOrLow) {
                $prices = array_merge($prices, self::gbmSegment($startOpen, $targetHigh, $seg1, $sigma));
                $prices = array_merge($prices, self::gbmSegment($targetHigh, $targetLow, $seg2, $sigma, 0));
                $prices = array_merge($prices, self::gbmSegment($targetLow, $endClose, $seg3, $sigma));
            } else {
                $prices = array_merge($prices, self::gbmSegment($startOpen, $targetLow, $seg1, $sigma, 0));
                $prices = array_merge($prices, self::gbmSegment($targetLow, $targetHigh, $seg2, $sigma));
                $prices = array_merge($prices, self::gbmSegment($targetHigh, $endClose, $seg3, $sigma, 0));
            }
            $prices = self::verifyData($prices, $targetHigh, $targetLow);
            // 根据价格序列构造K线数据
            $candles = [];
            $time    = $start;
            for ($i = 0, $n = count($prices) - 1; $i < $n; $i++) {
                $open  = $prices[$i];
                $close = $prices[$i + 1];
                $high  = max($open, $close);
                $low   = min($open, $close);
                
                // 在高低价上加入随机波动以增强真实性
//                $high      += (random_int(0, 10) / 100) * $sigma * $high;
//                $low       -= (random_int(0, 10) / 100) * $sigma * $low;
                // 格式化成 $scale 小数
                
                $candles[] = [
                    'open'      => number_format($open, $scale),
                    'high'      => number_format($high, $scale),
                    'low'       => number_format($low, $scale),
                    'close'     => number_format($i == ($n - 1) ? $endClose : $close, $scale),
                    'timestamp' => $time->copy()->timestamp * 1000,
                ];
                $time      = $time->addSeconds($intervalSeconds);
            }
            return $candles;
        } catch (RandomException $e) {
            return [];
        }
    }
    
    
    /** 生成带“终点约束”的 GBM 段（对数空间线性引导 + 高斯噪声） */
    private static function gbmSegment(float $startPrice, float $endPrice, int $steps, float $sigma, int $direction = 1): array
    {
        $path     = [];
        $logStart = log(max($startPrice, 1e-8));
        $logEnd   = log(max($endPrice, 1e-8));
        
        for ($i = 0; $i < $steps; $i++) {
            $remaining = $steps - $i;
            $drift     = ($logEnd - $logStart) / max(1, $remaining); // 引导到目标终点
            $noise     = $sigma * self::randn();                     // 高斯扰动
            $logStart  += $drift + $noise;
            
            // 价格保持正
            $price = max(0.0001, exp($logStart));
            if ($direction) {
                if ($price < self::$targetLow) {
                    $price = self::$targetLow;
                } else if ($price > self::$targetHigh) {
                    $price = self::$targetHigh;
                }
            } else {
                if ($price > self::$targetHigh) {
                    $price = self::$targetHigh;
                } else if ($price < self::$targetLow) {
                    $price = self::$targetLow;
                }
            }
            $path[] = $price;
        }
        return $path;
    }
    
    /** 标准正态（Box–Muller） */
    private static function randn(): float
    {
        $u1 = max(1e-12, mt_rand() / mt_getrandmax());
        $u2 = mt_rand() / mt_getrandmax();
        return sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
    }
    
    /** 有界 OU 段（logit 变换 + 终点引导 + 桥形降波），保证价格在 [low, high] 徘徊 */
    private static function rangeBoundSegment(
        float $startPrice,
        float $endPrice,
        int   $steps,
        float $low,
        float $high,
        float $sigma = 0.0001,   // 噪声强度（区间内的“抖动”）
        float $kappa = 3.0     // 回复强度（越大越贴近目标与中线，波动更温和）
    ): array
    {
        $eps = 1e-6;
        if ($low > $high) {
            [$low, $high] = [$high, $low];
        }
        $w = max($high - $low, $eps);
        
        // 规范化到 (0,1)，再做 logit
        $toX   = function (float $p) use ($low, $w, $eps): float {
            return min(max(($p - $low) / $w, $eps), 1.0 - $eps);
        };
        $logit = fn(float $x): float => log($x / (1.0 - $x));
        $sigm  = fn(float $z): float => 1.0 / (1.0 + exp(-$z));
        
        $x0   = $toX($startPrice);
        $xT   = $toX($endPrice);
        $z    = $logit($x0);
        $zEnd = $logit($xT);
        
        $path = [];
        $dt   = 1.0 / max(1, $steps);
        
        for ($i = 1; $i <= $steps; $i++) {
            $t = $i / $steps;
            
            // 终点引导：z 在 [z, zEnd] 间线性过渡
            $guide = (1.0 - $t) * $z + $t * $zEnd;
            
            // OU 风格的回复（对 guide 与中线都有回拉效果）
            $alpha = exp(-$kappa * $dt);
            $zMean = $alpha * $z + (1.0 - $alpha) * $guide;
            
            // 桥形波动：中间大、两端小；步数越多，每步噪声越小
            $bridgeShape = max(1e-6, sqrt($t * (1.0 - $t)));
            $z           = $zMean + $sigma * sqrt($dt) * $bridgeShape * self::randn();
            
            // 映射回价格并做稳妥裁剪
            $x = $sigm($z);
            $p = $low + $w * $x;
            if ($p <= $low) {
                $p = $low + 1e-6;
            }
            if ($p >= $high) {
                $p = $high - 1e-6;
            }
            
            $path[] = $p;
        }
        return $path;
    }
    
    
    private static function verifyData(array $data, float $high, float $low): array
    {
        $max       = $data[0];
        $min       = $data[0];
        $highIndex = 0;
        $lowIndex  = 0;
        foreach ($data as $i => $price) {
            if ($price > $max) {
                $max       = $price;
                $highIndex = $i;
            }
            if ($price < $min) {
                $min      = $price;
                $lowIndex = $i;
            }
        }
        if ($max < $high) {
            $data[$highIndex] = $high;
        }
        if ($min > $low) {
            $data[$lowIndex] = $low;
        }
        return $data;
    }
}
