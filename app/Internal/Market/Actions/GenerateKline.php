<?php

namespace Internal\Market\Actions;

use App\Exceptions\LogicException;
use Exception;

class GenerateKline {
    private $symbol;
    private $startTime;
    private $endTime;
    private $highPrice;
    private $lowPrice;
    private $startPrice;
    private $endPrice;
    private $keyPoints;
    private $intervals = [
        '1m' => 60,
        '5m' => 300,
        '15m' => 900,
        '30m' => 1800,
        '1h' => 3600,
        '4h' => 14400,
        '1d' => 86400,
        '1w' => 604800,
        '1month' => 2592000
    ];
    
    public function __construct($startTime, $endTime, $highPrice, $lowPrice, $startPrice, $endPrice) {
        $this->startTime = is_numeric($startTime) ? $startTime : strtotime($startTime);
        $this->endTime = is_numeric($endTime) ? $endTime : strtotime($endTime);
        $this->highPrice = $highPrice;
        $this->lowPrice = $lowPrice;
        $this->startPrice = $startPrice;
        $this->endPrice = $endPrice;
        
        // 预生成关键点，用于所有interval共享
        $this->generateKeyPoints();
    }
    
    /**
     * 生成指定interval的K线数据（生成器模式）
     * @param string $interval 时间间隔 (1m, 5m, 15m等)
     * @return Generator
     */
    public function generateKlines($interval) {
        if (!isset($this->intervals[$interval])) {
            throw new Exception("不支持的interval: {$interval}");
        }
        
        $intervalSeconds = $this->intervals[$interval];
        
        // 对于大于1分钟的interval，使用聚合方式
        if ($intervalSeconds > 60) {
            yield from $this->generateAggregatedKlines($interval);
        } else {
            yield from $this->generateMinuteKlines();
        }
    }
    
    /**
     * 流式生成1分钟K线数据
     */
    private function generateMinuteKlines() {
        $duration = $this->endTime - $this->startTime;
        $totalMinutes = intval($duration / 60);
        
        if ($totalMinutes <= 0) {
            return;
        }
        
        $prevClose = $this->startPrice;
        $priceRange = $this->highPrice - $this->lowPrice;
        
        for ($i = 0; $i < $totalMinutes; $i++) {
            $timestamp = $this->startTime + ($i * 60);
            
            // 计算当前时间点的趋势价格
            $trendPrice = $this->calculateTrendPrice($i, $totalMinutes);
            
            // 增加波动性，使K线更自然
            // 基础波动率为价格范围的2-5%
            $baseVolatility = $priceRange * (0.02 + rand(0, 300) / 10000);
            $noise = $this->generateNoise() * $baseVolatility;
            
            // 生成收盘价
            $close = $trendPrice + $noise;
            
            // 最后一分钟确保收盘价等于结束价格
            if ($i == $totalMinutes - 1) {
                $close = $this->endPrice;
            }
            
            // 确保收盘价在范围内，但允许偶尔接近边界
            $close = max($this->lowPrice, min($this->highPrice, $close));
            
            // 生成开盘价（使用前一根K线的收盘价）
            $open = $prevClose;
            
            // 生成high和low（增加K线实体的变化）
            // K线内部波动为基础波动的30-80%
            $intraVolatility = $baseVolatility * (0.3 + rand(0, 50) / 100);
            
            // 计算K线的上下影线
            $upperShadow = abs($this->generateNoise() * $intraVolatility * (0.5 + rand(0, 100) / 100));
            $lowerShadow = abs($this->generateNoise() * $intraVolatility * (0.5 + rand(0, 100) / 100));
            
            // 设置high和low
            $high = max($open, $close) + $upperShadow;
            $low = min($open, $close) - $lowerShadow;
            
            // 偶尔生成长影线（10%概率）
            if (rand(1, 100) <= 10) {
                if (rand(0, 1) == 0) {
                    $high += $intraVolatility * (1 + rand(0, 100) / 100);
                } else {
                    $low -= $intraVolatility * (1 + rand(0, 100) / 100);
                }
            }
            
            // 确保high和low在允许范围内
            $high = min($this->highPrice, $high);
            $low = max($this->lowPrice, $low);
            
            // 确保逻辑关系正确
            if ($high < max($open, $close)) {
                $high = max($open, $close);
            }
            if ($low > min($open, $close)) {
                $low = min($open, $close);
            }
            
            $volume = $this->generateVolume($i, $totalMinutes);
            
            $prevClose = $close;
            
            yield [
                'tl' => $timestamp * 1000,  // 转换为毫秒时间戳
                'o' => round($open, 4),
                'h' => round($high, 4),
                'l' => round($low, 4),
                'c' => round($close, 4),
                'v' => intval($volume)
            ];
        }
    }
    
    /**
     * 流式生成聚合的K线数据
     */
    private function generateAggregatedKlines($interval) {
        $intervalSeconds = $this->intervals[$interval];
        $bucketData = [];
        $bucketStartTime = null;
        
        foreach ($this->generateMinuteKlines() as $minute) {
            $minuteTimestamp = intval($minute['tl'] / 1000);  // 转回秒
            
            if ($bucketStartTime === null) {
                $bucketStartTime = floor($minuteTimestamp / $intervalSeconds) * $intervalSeconds;
            }
            
            $minuteBucketTime = floor($minuteTimestamp / $intervalSeconds) * $intervalSeconds;
            
            if ($minuteBucketTime != $bucketStartTime) {
                // 输出当前bucket
                if (!empty($bucketData)) {
                    yield $this->createAggregatedCandle($bucketData, $bucketStartTime);
                }
                
                // 开始新bucket
                $bucketData = [$minute];
                $bucketStartTime = $minuteBucketTime;
            } else {
                $bucketData[] = $minute;
            }
        }
        
        // 输出最后一个bucket
        if (!empty($bucketData)) {
            yield $this->createAggregatedCandle($bucketData, $bucketStartTime);
        }
    }
    
    /**
     * 将K线数据导出到CSV文件（特定格式）
     * @param string $interval 时间间隔
     * @param string $filename CSV文件名
     * @param bool $append 是否追加到文件
     */
    public function exportToCSV($interval, $filename, $append = false) {
        $mode = $append ? 'a' : 'w';
        $file = fopen($filename, $mode);
        if (!$file) {
            throw new Exception("无法创建文件: {$filename}");
        }
        
        // 如果是新文件，写入表头
        if (!$append || filesize($filename) == 0) {
            // 写入固定表头
            fputcsv($file, ['#datatype measurement','tag','tag','string','dateTime:number']);
            fputcsv($file, ['name','symbol','interval','content']);
        }
        
        // 流式写入数据
        foreach ($this->generateKlines($interval) as $kline) {
            $jsonContent = json_encode([
                'o' => sprintf('%.4f', $kline['o']),
                'h' => sprintf('%.4f', $kline['h']),
                'l' => sprintf('%.4f', $kline['l']),
                'c' => sprintf('%.4f', $kline['c']),
                'v' => $kline['v'],
                'tl' => strval($kline['tl'])  // 时间戳作为字符串
            ], JSON_UNESCAPED_SLASHES);
            fputcsv($file, ['kline',$this->symbol,$interval, $jsonContent, strval($kline['tl'])]);
        }
        
        fclose($file);
        
        return true;
    }
    
    /**
     * 批量导出多个interval到同一个CSV文件
     * @param array $intervals 要导出的interval列表
     * @param string $filename CSV文件名
     */
    public function exportMultipleToSingleCSV($intervals, $filename) {
        $file = fopen($filename, 'w');
        if (!$file) {
            throw new Exception("无法创建文件: {$filename}");
        }
        
        // 写入表头
        fputcsv($file, ['interval', 'content']);
        
        $results = [];
        foreach ($intervals as $interval) {
            try {
                $count = 0;
                foreach ($this->generateKlines($interval) as $kline) {
                    $jsonContent = json_encode([
                        'o' => sprintf('%.4f', $kline['o']),
                        'h' => sprintf('%.4f', $kline['h']),
                        'l' => sprintf('%.4f', $kline['l']),
                        'c' => sprintf('%.4f', $kline['c']),
                        'v' => $kline['v'],
                        'tl' => strval($kline['tl'])
                    ], JSON_UNESCAPED_SLASHES);
                    
                    fputcsv($file, [$interval, $jsonContent]);
                    $count++;
                }
                
                $results[$interval] = [
                    'status' => 'success',
                    'count' => $count
                ];
                echo "已生成 {$interval} K线数据: {$count} 条\n";
                
            } catch (Exception $e) {
                $results[$interval] = [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                echo "生成 {$interval} K线数据失败: " . $e->getMessage() . "\n";
            }
        }
        
        fclose($file);
        
        return $results;
    }
    
    /**
     * 批量导出多个interval到各自的CSV文件
     * @param array $intervals 要导出的interval列表
     * @param string $prefix 文件名前缀
     */
    public function exportMultipleCSV($intervals, $prefix = 'kline') {
        $results = [];
        
        foreach ($intervals as $interval) {
            $filename = "{$prefix}_{$interval}.csv";
            try {
                $this->exportToCSV($interval, $filename);
                $results[$interval] = [
                    'status' => 'success',
                    'filename' => $filename
                ];
                echo "已生成 {$interval} K线数据: {$filename}\n";
            } catch (Exception $e) {
                $results[$interval] = [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                echo "生成 {$interval} K线数据失败: " . $e->getMessage() . "\n";
            }
        }
        
        return $results;
    }
    
    /**
     * 获取指定interval的K线数量（不生成实际数据）
     */
    public function getKlineCount($interval) {
        if (!isset($this->intervals[$interval])) {
            throw new Exception("不支持的interval: {$interval}");
        }
        
        $intervalSeconds = $this->intervals[$interval];
        $duration = $this->endTime - $this->startTime;
        
        return intval($duration / $intervalSeconds);
    }
    
    /**
     * 流式处理K线数据（自定义处理函数）
     * @param string $interval 时间间隔
     * @param callable $processor 处理函数
     */
    public function processKlines($interval, callable $processor) {
        $index = 0;
        foreach ($this->generateKlines($interval) as $kline) {
            $processor($kline, $index++);
        }
    }
    
    /**
     * 获取格式化的K线数据（用于API返回）
     * @param string $interval 时间间隔
     * @param int $limit 限制返回数量
     * @return array
     */
    public function getFormattedKlines($interval, $limit = null) {
        $result = [];
        $count = 0;
        
        foreach ($this->generateKlines($interval) as $kline) {
            $result[] = [
                'interval' => $interval,
                'content' => json_encode([
                    'o' => sprintf('%.4f', $kline['o']),
                    'h' => sprintf('%.4f', $kline['h']),
                    'l' => sprintf('%.4f', $kline['l']),
                    'c' => sprintf('%.4f', $kline['c']),
                    'v' => $kline['v'],
                    'tl' => strval($kline['tl'])
                ], JSON_UNESCAPED_SLASHES)
            ];
            
            $count++;
            if ($limit !== null && $count >= $limit) {
                break;
            }
        }
        
        return $result;
    }
    
    /**
     * 预生成价格关键点
     */
    private function generateKeyPoints() {
        $duration = $this->endTime - $this->startTime;
        $totalMinutes = intval($duration / 60);
        
        if ($totalMinutes <= 0) {
            $this->keyPoints = [];
            return;
        }
        
        $keyPoints = [];
        $priceRange = $this->highPrice - $this->lowPrice;
        
        // 添加起始点
        $keyPoints[] = ['time' => 0, 'price' => $this->startPrice];
        
        // 生成更多的中间关键点，让价格波动更丰富
        $numKeyPoints = min(20, max(5, intval($totalMinutes / 30))); // 每30分钟约1个关键点
        
        // 确保最高点和最低点都会被触及
        // 随机决定最高点和最低点的位置
        $highPointTime = rand(intval($totalMinutes * 0.15), intval($totalMinutes * 0.85));
        $lowPointTime = rand(intval($totalMinutes * 0.15), intval($totalMinutes * 0.85));
        
        // 确保高低点不在同一时间，且有足够距离
        while (abs($highPointTime - $lowPointTime) < $totalMinutes * 0.2) {
            $lowPointTime = rand(intval($totalMinutes * 0.15), intval($totalMinutes * 0.85));
        }
        
        // 添加最高点和最低点（稍微偏离一点，让它更自然）
        $highOffset = $priceRange * (rand(0, 50) / 1000); // 0-5%的偏移
        $lowOffset = $priceRange * (rand(0, 50) / 1000);
        
        $keyPoints[] = ['time' => $highPointTime, 'price' => $this->highPrice - $highOffset];
        $keyPoints[] = ['time' => $lowPointTime, 'price' => $this->lowPrice + $lowOffset];
        
        // 添加其他随机关键点，分布在整个价格区间
        for ($i = 0; $i < $numKeyPoints - 2; $i++) {
            $time = rand(1, $totalMinutes - 1);
            
            // 使用不同的分布策略，让价格更分散
            $randomFactor = rand(0, 100) / 100;
            
            // 30%的点在上半区
            if ($randomFactor < 0.3) {
                $price = $this->lowPrice + $priceRange * (0.6 + 0.4 * rand(0, 100) / 100);
            }
            // 30%的点在下半区
            elseif ($randomFactor < 0.6) {
                $price = $this->lowPrice + $priceRange * (0.0 + 0.4 * rand(0, 100) / 100);
            }
            // 40%的点在中间区域
            else {
                $price = $this->lowPrice + $priceRange * (0.3 + 0.4 * rand(0, 100) / 100);
            }
            
            $keyPoints[] = ['time' => $time, 'price' => $price];
        }
        
        // 添加结束点
        $keyPoints[] = ['time' => $totalMinutes, 'price' => $this->endPrice];
        
        // 按时间排序
        usort($keyPoints, function($a, $b) {
            return $a['time'] - $b['time'];
        });
        
        $this->keyPoints = $keyPoints;
    }
    
    /**
     * 计算特定时间点的趋势价格
     */
    private function calculateTrendPrice($currentMinute, $totalMinutes) {
        $prevPoint = null;
        $nextPoint = null;
        
        foreach ($this->keyPoints as $point) {
            if ($point['time'] <= $currentMinute) {
                $prevPoint = $point;
            }
            if ($point['time'] >= $currentMinute && $nextPoint === null) {
                $nextPoint = $point;
                break;
            }
        }
        
        if ($prevPoint && $nextPoint && $prevPoint['time'] != $nextPoint['time']) {
            $t = ($currentMinute - $prevPoint['time']) / ($nextPoint['time'] - $prevPoint['time']);
            
            // 使用更复杂的插值，增加价格变化的多样性
            // 添加一些随机性，让价格路径不那么平滑
            $randomFactor = 1 + (rand(-100, 100) / 1000); // ±10%的随机因子
            
            // 使用正弦波调制，创建更自然的波动
            $sineModulation = sin($t * pi() * 2 * rand(1, 3)) * 0.1;
            
            // 基础插值
            $t2 = $t * $t;
            $t3 = $t2 * $t;
            
            // Hermite插值
            $h00 = 2 * $t3 - 3 * $t2 + 1;
            $h01 = -2 * $t3 + 3 * $t2;
            
            $basePrice = $h00 * $prevPoint['price'] + $h01 * $nextPoint['price'];
            
            // 添加调制，使价格更有变化
            $price = $basePrice * $randomFactor + ($nextPoint['price'] - $prevPoint['price']) * $sineModulation;
            
            return $price;
        }
        
        return $this->startPrice;
    }
    
    /**
     * 生成噪声
     */
    private function generateNoise() {
        $u1 = rand(0, 10000) / 10000;
        $u2 = rand(0, 10000) / 10000;
        
        if ($u1 == 0) $u1 = 0.0001;
        
        $z0 = sqrt(-2 * log($u1)) * cos(2 * pi() * $u2);
        
        return $z0;
    }
    
    /**
     * 生成成交量
     */
    private function generateVolume($currentMinute, $totalMinutes) {
        $baseVolume = 10000 + rand(0, 50000);
        $timeRatio = $currentMinute / $totalMinutes;
        $timeFactor = 1;
        
        if ($timeRatio < 0.1 || $timeRatio > 0.9) {
            $timeFactor = 1.5 + rand(0, 100) / 100;
        }
        
        $randomFactor = 0.5 + rand(0, 150) / 100;
        
        return round($baseVolume * $timeFactor * $randomFactor);
    }
    
    /**
     * 创建聚合后的K线
     */
    private function createAggregatedCandle($candles, $timestamp) {
        $open = $candles[0]['o'];
        $close = $candles[count($candles) - 1]['c'];
        $high = max(array_column($candles, 'h'));
        $low = min(array_column($candles, 'l'));
        $volume = array_sum(array_column($candles, 'v'));
        
        return [
            'tl' => $timestamp * 1000,  // 毫秒时间戳
            'o' => $open,
            'h' => $high,
            'l' => $low,
            'c' => $close,
            'v' => intval($volume)
        ];
    }

    // private $startTime;
    // private $endTime;
    // private $highPrice;
    // private $lowPrice;
    // private $startPrice;
    // private $endPrice;
    // private $keyPoints;
    // private $intervals = [
    //     '1m' => 60,
    //     '5m' => 300,
    //     '15m' => 900,
    //     '30m' => 1800,
    //     '1h' => 3600,
    //     '4h' => 14400,
    //     '1d' => 86400,
    //     '1w' => 604800,
    //     '1month' => 2592000
    // ];

    // public function __construct($symbol, $startTime, $endTime, $highPrice, $lowPrice, $startPrice, $endPrice) {
    //     $this->symbol = $symbol;
    //     $this->startTime = is_numeric($startTime) ? $startTime : strtotime($startTime);
    //     $this->endTime = is_numeric($endTime) ? $endTime : strtotime($endTime);
    //     $this->highPrice = $highPrice;
    //     $this->lowPrice = $lowPrice;
    //     $this->startPrice = $startPrice;
    //     $this->endPrice = $endPrice;
        
    //     // 预生成关键点，用于所有interval共享
    //     $this->generateKeyPoints();
    // }
    
    // /**
    //  * 生成指定interval的K线数据（生成器模式）
    //  * @param string $interval 时间间隔 (1m, 5m, 15m等)
    //  * @return Generator
    //  */
    // public function generateKlines($interval) {
    //     if (!isset($this->intervals[$interval])) {
    //         throw new Exception("不支持的interval: {$interval}");
    //     }
        
    //     $intervalSeconds = $this->intervals[$interval];
        
    //     // 对于大于1分钟的interval，使用聚合方式
    //     if ($intervalSeconds > 60) {
    //         yield from $this->generateAggregatedKlines($interval);
    //     } else {
    //         yield from $this->generateMinuteKlines();
    //     }
    // }
    
    // /**
    //  * 流式生成1分钟K线数据
    //  */
    // private function generateMinuteKlines() {
    //     $duration = $this->endTime - $this->startTime;
    //     $totalMinutes = intval($duration / 60);
        
    //     if ($totalMinutes <= 0) {
    //         return;
    //     }
        
    //     $prevClose = $this->startPrice;
        
    //     for ($i = 0; $i < $totalMinutes; $i++) {
    //         $timestamp = $this->startTime + ($i * 60);
            
    //         // 计算当前时间点的趋势价格
    //         $trendPrice = $this->calculateTrendPrice($i, $totalMinutes);
            
    //         // 添加随机波动
    //         $volatility = ($this->highPrice - $this->lowPrice) * 0.006;
    //         $noise = $this->generateNoise() * $volatility;
            
    //         // 生成OHLC
    //         $open = $prevClose;
    //         $close = max($this->lowPrice, min($this->highPrice, $trendPrice + $noise));
            
    //         // 最后一分钟确保收盘价等于结束价格
    //         if ($i == $totalMinutes - 1) {
    //             $close = $this->endPrice;
    //         }
            
    //         // 生成high和low
    //         $intraVolatility = $volatility * 0.5;
    //         $high = max($open, $close) + abs($this->generateNoise() * $intraVolatility);
    //         $low = min($open, $close) - abs($this->generateNoise() * $intraVolatility);
            
    //         $high = min($this->highPrice, $high);
    //         $low = max($this->lowPrice, $low);
            
    //         $volume = $this->generateVolume($i, $totalMinutes);
            
    //         $prevClose = $close;
            
    //         yield [
    //             'tl' => $timestamp * 1000,  // 转换为毫秒时间戳
    //             'o' => round($open, 4),
    //             'h' => round($high, 4),
    //             'l' => round($low, 4),
    //             'c' => round($close, 4),
    //             'v' => intval($volume)
    //         ];
    //     }
    // }
    
    // /**
    //  * 流式生成聚合的K线数据
    //  */
    // private function generateAggregatedKlines($interval) {
    //     $intervalSeconds = $this->intervals[$interval];
    //     $bucketData = [];
    //     $bucketStartTime = null;
        
    //     foreach ($this->generateMinuteKlines() as $minute) {
    //         $minuteTimestamp = intval($minute['tl'] / 1000);  // 转回秒
            
    //         if ($bucketStartTime === null) {
    //             $bucketStartTime = floor($minuteTimestamp / $intervalSeconds) * $intervalSeconds;
    //         }
            
    //         $minuteBucketTime = floor($minuteTimestamp / $intervalSeconds) * $intervalSeconds;
            
    //         if ($minuteBucketTime != $bucketStartTime) {
    //             // 输出当前bucket
    //             if (!empty($bucketData)) {
    //                 yield $this->createAggregatedCandle($bucketData, $bucketStartTime);
    //             }
                
    //             // 开始新bucket
    //             $bucketData = [$minute];
    //             $bucketStartTime = $minuteBucketTime;
    //         } else {
    //             $bucketData[] = $minute;
    //         }
    //     }
        
    //     // 输出最后一个bucket
    //     if (!empty($bucketData)) {
    //         yield $this->createAggregatedCandle($bucketData, $bucketStartTime);
    //     }
    // }
    
    // /**
    //  * 将K线数据导出到CSV文件（特定格式）
    //  * @param string $interval 时间间隔
    //  * @param string $filename CSV文件名
    //  * @param bool $append 是否追加到文件
    //  */
    // public function exportToCSV($interval, $filename, $append = false) {
    //     $mode = $append ? 'a' : 'w';
    //     $file = fopen($filename, $mode);
    //     if (!$file) {
    //         throw new Exception("无法创建文件: {$filename}");
    //     }
        
    //     // 如果是新文件，写入表头
    //     if (!$append || filesize($filename) == 0) {
    //         // 写入固定表头
    //         fputcsv($file, ['#datatype measurement','tag','tag','string','dateTime:number']);
    //         fputcsv($file, ['name','symbol','interval','content']);
    //     }
        
    //     // 流式写入数据
    //     foreach ($this->generateKlines($interval) as $kline) {
    //         $jsonContent = json_encode([
    //             'o' => sprintf('%.4f', $kline['o']),
    //             'h' => sprintf('%.4f', $kline['h']),
    //             'l' => sprintf('%.4f', $kline['l']),
    //             'c' => sprintf('%.4f', $kline['c']),
    //             'v' => $kline['v'],
    //             'tl' => strval($kline['tl'])  // 时间戳作为字符串
    //         ], JSON_UNESCAPED_SLASHES);
    //         fputcsv($file, ['kline',$this->symbol,$interval, $jsonContent, strval($kline['tl'])]);
    //     }
        
    //     fclose($file);
        
    //     return true;
    // }
    
    // /**
    //  * 批量导出多个interval到同一个CSV文件
    //  * @param array $intervals 要导出的interval列表
    //  * @param string $filename CSV文件名
    //  */
    // public function exportMultipleToSingleCSV($intervals, $filename) {
    //     $file = fopen($filename, 'w');
    //     if (!$file) {
    //         throw new Exception("无法创建文件: {$filename}");
    //     }
        
    //     // 写入表头
    //     fputcsv($file, ['interval', 'content']);
        
    //     $results = [];
    //     foreach ($intervals as $interval) {
    //         try {
    //             $count = 0;
    //             foreach ($this->generateKlines($interval) as $kline) {
    //                 $jsonContent = json_encode([
    //                     'o' => sprintf('%.4f', $kline['o']),
    //                     'h' => sprintf('%.4f', $kline['h']),
    //                     'l' => sprintf('%.4f', $kline['l']),
    //                     'c' => sprintf('%.4f', $kline['c']),
    //                     'v' => $kline['v'],
    //                     'tl' => strval($kline['tl'])
    //                 ], JSON_UNESCAPED_SLASHES);
                    
    //                 fputcsv($file, [$interval, $jsonContent]);
    //                 $count++;
    //             }
                
    //             $results[$interval] = [
    //                 'status' => 'success',
    //                 'count' => $count
    //             ];
    //             echo "已生成 {$interval} K线数据: {$count} 条\n";
                
    //         } catch (Exception $e) {
    //             $results[$interval] = [
    //                 'status' => 'error',
    //                 'message' => $e->getMessage()
    //             ];
    //             echo "生成 {$interval} K线数据失败: " . $e->getMessage() . "\n";
    //         }
    //     }
        
    //     fclose($file);
        
    //     return $results;
    // }
    
    // /**
    //  * 批量导出多个interval到各自的CSV文件
    //  * @param array $intervals 要导出的interval列表
    //  * @param string $prefix 文件名前缀
    //  */
    // public function exportMultipleCSV($intervals, $prefix = 'kline') {
    //     $results = [];
        
    //     foreach ($intervals as $interval) {
    //         $filename = "{$prefix}_{$interval}.csv";
    //         try {
    //             $this->exportToCSV($interval, $filename);
    //             $results[$interval] = [
    //                 'status' => 'success',
    //                 'filename' => $filename
    //             ];
    //             echo "已生成 {$interval} K线数据: {$filename}\n";
    //         } catch (Exception $e) {
    //             $results[$interval] = [
    //                 'status' => 'error',
    //                 'message' => $e->getMessage()
    //             ];
    //             echo "生成 {$interval} K线数据失败: " . $e->getMessage() . "\n";
    //         }
    //     }
        
    //     return $results;
    // }
    
    // /**
    //  * 获取指定interval的K线数量（不生成实际数据）
    //  */
    // public function getKlineCount($interval) {
    //     if (!isset($this->intervals[$interval])) {
    //         throw new Exception("不支持的interval: {$interval}");
    //     }
        
    //     $intervalSeconds = $this->intervals[$interval];
    //     $duration = $this->endTime - $this->startTime;
        
    //     return intval($duration / $intervalSeconds);
    // }
    
    // /**
    //  * 流式处理K线数据（自定义处理函数）
    //  * @param string $interval 时间间隔
    //  * @param callable $processor 处理函数
    //  */
    // public function processKlines($interval, callable $processor) {
    //     $index = 0;
    //     foreach ($this->generateKlines($interval) as $kline) {
    //         $processor($kline, $index++);
    //     }
    // }
    
    // /**
    //  * 获取格式化的K线数据（用于API返回）
    //  * @param string $interval 时间间隔
    //  * @param int $limit 限制返回数量
    //  * @return array
    //  */
    // public function getFormattedKlines($interval, $limit = null) {
    //     $result = [];
    //     $count = 0;
        
    //     foreach ($this->generateKlines($interval) as $kline) {
    //         $result[] = [
    //             'interval' => $interval,
    //             'content' => json_encode([
    //                 'o' => sprintf('%.4f', $kline['o']),
    //                 'h' => sprintf('%.4f', $kline['h']),
    //                 'l' => sprintf('%.4f', $kline['l']),
    //                 'c' => sprintf('%.4f', $kline['c']),
    //                 'v' => $kline['v'],
    //                 'tl' => strval($kline['tl'])
    //             ], JSON_UNESCAPED_SLASHES)
    //         ];
            
    //         $count++;
    //         if ($limit !== null && $count >= $limit) {
    //             break;
    //         }
    //     }
        
    //     return $result;
    // }
    
    // /**
    //  * 预生成价格关键点
    //  */
    // private function generateKeyPoints() {
    //     $duration = $this->endTime - $this->startTime;
    //     $totalMinutes = intval($duration / 60);
        
    //     if ($totalMinutes <= 0) {
    //         $this->keyPoints = [];
    //         return;
    //     }
        
    //     $keyPoints = [];
        
    //     // 添加起始点
    //     $keyPoints[] = ['time' => 0, 'price' => $this->startPrice];
        
    //     // 生成中间关键点
    //     $numKeyPoints = min(10, max(3, intval($totalMinutes / 60)));
        
    //     // 随机决定最高点和最低点的位置
    //     $highPointTime = rand(intval($totalMinutes * 0.2), intval($totalMinutes * 0.8));
    //     $lowPointTime = rand(intval($totalMinutes * 0.2), intval($totalMinutes * 0.8));
        
    //     while (abs($highPointTime - $lowPointTime) < $totalMinutes * 0.1) {
    //         $lowPointTime = rand(intval($totalMinutes * 0.2), intval($totalMinutes * 0.8));
    //     }
        
    //     $keyPoints[] = ['time' => $highPointTime, 'price' => $this->highPrice];
    //     $keyPoints[] = ['time' => $lowPointTime, 'price' => $this->lowPrice];
        
    //     // 添加其他随机关键点
    //     for ($i = 0; $i < $numKeyPoints - 2; $i++) {
    //         $time = rand(1, $totalMinutes - 1);
    //         $price = $this->lowPrice + ($this->highPrice - $this->lowPrice) * (0.2 + 0.6 * rand(0, 100) / 100);
    //         $keyPoints[] = ['time' => $time, 'price' => $price];
    //     }
        
    //     // 添加结束点
    //     $keyPoints[] = ['time' => $totalMinutes, 'price' => $this->endPrice];
        
    //     // 按时间排序
    //     usort($keyPoints, function($a, $b) {
    //         return $a['time'] - $b['time'];
    //     });
        
    //     $this->keyPoints = $keyPoints;
    // }
    
    // /**
    //  * 计算特定时间点的趋势价格
    //  */
    // private function calculateTrendPrice($currentMinute, $totalMinutes) {
    //     $prevPoint = null;
    //     $nextPoint = null;
        
    //     foreach ($this->keyPoints as $point) {
    //         if ($point['time'] <= $currentMinute) {
    //             $prevPoint = $point;
    //         }
    //         if ($point['time'] >= $currentMinute && $nextPoint === null) {
    //             $nextPoint = $point;
    //             break;
    //         }
    //     }
        
    //     if ($prevPoint && $nextPoint && $prevPoint['time'] != $nextPoint['time']) {
    //         $t = ($currentMinute - $prevPoint['time']) / ($nextPoint['time'] - $prevPoint['time']);
    //         $t2 = $t * $t;
    //         $t3 = $t2 * $t;
            
    //         $h00 = 2 * $t3 - 3 * $t2 + 1;
    //         $h01 = -2 * $t3 + 3 * $t2;
            
    //         $price = $h00 * $prevPoint['price'] + $h01 * $nextPoint['price'];
            
    //         return $price;
    //     }
        
    //     return $this->startPrice;
    // }
    
    // /**
    //  * 生成噪声
    //  */
    // private function generateNoise() {
    //     $u1 = rand(0, 10000) / 10000;
    //     $u2 = rand(0, 10000) / 10000;
        
    //     if ($u1 == 0) $u1 = 0.0001;
        
    //     $z0 = sqrt(-2 * log($u1)) * cos(2 * pi() * $u2);
        
    //     return $z0;
    // }
    
    // /**
    //  * 生成成交量
    //  */
    // private function generateVolume($currentMinute, $totalMinutes) {
    //     $baseVolume = 10000 + rand(0, 50000);
    //     $timeRatio = $currentMinute / $totalMinutes;
    //     $timeFactor = 1;
        
    //     if ($timeRatio < 0.1 || $timeRatio > 0.9) {
    //         $timeFactor = 1.5 + rand(0, 100) / 100;
    //     }
        
    //     $randomFactor = 0.5 + rand(0, 150) / 100;
        
    //     return round($baseVolume * $timeFactor * $randomFactor);
    // }
    
    // /**
    //  * 创建聚合后的K线
    //  */
    // private function createAggregatedCandle($candles, $timestamp) {
    //     $open = $candles[0]['o'];
    //     $close = $candles[count($candles) - 1]['c'];
    //     $high = max(array_column($candles, 'h'));
    //     $low = min(array_column($candles, 'l'));
    //     $volume = array_sum(array_column($candles, 'v'));
        
    //     return [
    //         'tl' => $timestamp * 1000,  // 毫秒时间戳
    //         'o' => $open,
    //         'h' => $high,
    //         'l' => $low,
    //         'c' => $close,
    //         'v' => intval($volume)
    //     ];
    // }

}
