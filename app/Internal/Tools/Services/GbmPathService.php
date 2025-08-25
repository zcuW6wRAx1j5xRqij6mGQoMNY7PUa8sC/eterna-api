<?php
declare(strict_types=1);

namespace App\Internal\Tools\Services;

use DateTimeInterface;
use Carbon\CarbonImmutable;
use Random\RandomException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final class GbmPathService {
    
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
     * @param ?bool                    $getPrices       是否获取价格数据，默认为false
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
        float                    $sigma = 0.005,
        int                      $intervalSeconds = 1,
        ?int                     $scale = 5,
        ?bool                    $getPrices = false
    ): array
    {
        try {
            // 解析开始和结束时间
            $start = Carbon::parse($startTime, config('app.timezone'));
            
            $end = Carbon::parse($endTime, config('app.timezone'));
            // 计算总分钟数和总步数
            $totalTime = max(0, $end->diffInSeconds($start));
            $steps     = max(3, intdiv(max(1, $totalTime), max(1, $intervalSeconds)));
            
            // 将整个时间段划分为三个阶段
            $seg1 = max(1, intdiv($steps, 3));
            $seg2 = max(1, intdiv($steps, 3));
            $seg3 = max(1, $steps - $seg1 - $seg2);
            
            // 定义边界，确保包容所有点
            $lo = min($targetLow, $startOpen, $endClose);
            $hi = max($targetHigh, $startOpen, $endClose);
            
            // 构造价格序列：起始价 + 三段GBM路径
            $prices = [$startOpen];
            
            // 随机选择先到 high 或 low
            $upOrLow = rand(0, 1);
            
            if ($upOrLow) {
                $path1                      = self::gbmSegment($startOpen, $targetHigh, $seg1, $sigma, $lo, $hi);
                $prices                     = array_merge($prices, $path1);
                $prices[count($prices) - 1] = $targetHigh; // 强制段末命中目标
                
                $current                    = $prices[count($prices) - 1];
                $path2                      = self::gbmSegment($current, $targetLow, $seg2, $sigma, $lo, $hi);
                $prices                     = array_merge($prices, $path2);
                $prices[count($prices) - 1] = $targetLow;
                
            } else {
                $path1                      = self::gbmSegment($startOpen, $targetLow, $seg1, $sigma, $lo, $hi);
                $prices                     = array_merge($prices, $path1);
                $prices[count($prices) - 1] = $targetLow;
                
                $current                    = $prices[count($prices) - 1];
                $path2                      = self::gbmSegment($current, $targetHigh, $seg2, $sigma, $lo, $hi);
                $prices                     = array_merge($prices, $path2);
                $prices[count($prices) - 1] = $targetHigh;
                
            }
            $current = $prices[count($prices) - 1];
            $path3   = self::gbmSegment($current, $endClose, $seg3, $sigma, $lo, $hi);
            $prices  = array_merge($prices, $path3);
            
            // 确保整体极值命中（如果自然未触及）
            $prices = self::verifyData($prices, $targetHigh, $targetLow);
            
            // 是否直接获取价格数据
            if ($getPrices) {
                $prices[count($prices) - 1] = $endClose;
                return $prices;
            }
            
            // 根据价格序列构造K线数据
            $candles = [];
            $time    = $start;
            for ($i = 0, $n = count($prices) - 1; $i < $n; $i++) {
                $open  = $prices[$i];
                $close = $prices[$i + 1];
                $high  = max($open, $close);
                $low   = min($open, $close);
                
                // 在高低价上加入随机波动以增强真实性（可选，启用后需夹紧边界）
                // $high += (random_int(0, 10) / 100) * $sigma * $high;
                // $low -= (random_int(0, 10) / 100) * $sigma * $low;
                // $high = min($hi, max($high, $lo));
                // $low = max($lo, min($low, $hi));
                
                $candles[] = [
                    'open'      => number_format($open, $scale),
                    'high'      => number_format($high, $scale),
                    'low'       => number_format($low, $scale),
                    'close'     => number_format($i == ($n - 1) ? $endClose : $close, $scale),
                    'timestamp' => $time->copy()->timestamp * 1000,
                ];
                
                $time = $time->addSeconds($intervalSeconds);
            }
            return $candles;
        } catch (RandomException $e) {
            return [];
        }
    }
    
    
    /** 生成带“终点约束”和边界反射的 GBM 段（对数空间线性引导 + 高斯噪声 + 反射） */
    private static function gbmSegment(float $startPrice, float $endPrice, int $steps, float $sigma, float $low, float $high): array
    {
        $path     = [];
        $logStart = log(max($startPrice, 1e-8));
        $logEnd   = log(max($endPrice, 1e-8));
        $logLow   = log(max($low, 1e-8));
        $logHigh  = log(max($high, 1e-8));
        
        for ($i = 0; $i < $steps; $i++) {
            $remaining = $steps - $i;
            $drift     = ($logEnd - $logStart) / max(1, $remaining); // 引导到目标终点
            $noise     = $sigma * self::randn();                     // 高斯扰动
            $logStart  += $drift + $noise;
            
            // 反射以保持在边界内
            if ($logStart < $logLow) {
                $logStart = 2 * $logLow - $logStart;
            }
            if ($logStart > $logHigh) {
                $logStart = 2 * $logHigh - $logStart;
            }
            
            // 价格保持正
            $price  = max(0.0001, exp($logStart));
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
    
    // rangeBoundSegment 可以保留但未使用，因为反射 GBM 更适合
    
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