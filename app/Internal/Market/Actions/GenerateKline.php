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

    public function __construct($symbol, $startTime, $endTime, $highPrice, $lowPrice, $startPrice, $endPrice) {
        $this->symbol = $symbol;
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
        
        for ($i = 0; $i < $totalMinutes; $i++) {
            $timestamp = $this->startTime + ($i * 60);
            
            // 计算当前时间点的趋势价格
            $trendPrice = $this->calculateTrendPrice($i, $totalMinutes);
            
            // 添加随机波动
            $volatility = ($this->highPrice - $this->lowPrice) * 0.006;
            $noise = $this->generateNoise() * $volatility;
            
            // 生成OHLC
            $open = $prevClose;
            $close = max($this->lowPrice, min($this->highPrice, $trendPrice + $noise));
            
            // 最后一分钟确保收盘价等于结束价格
            if ($i == $totalMinutes - 1) {
                $close = $this->endPrice;
            }
            
            // 生成high和low
            $intraVolatility = $volatility * 0.5;
            $high = max($open, $close) + abs($this->generateNoise() * $intraVolatility);
            $low = min($open, $close) - abs($this->generateNoise() * $intraVolatility);
            
            $high = min($this->highPrice, $high);
            $low = max($this->lowPrice, $low);
            
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
        
        // 添加起始点
        $keyPoints[] = ['time' => 0, 'price' => $this->startPrice];
        
        // 生成中间关键点
        $numKeyPoints = min(10, max(3, intval($totalMinutes / 60)));
        
        // 随机决定最高点和最低点的位置
        $highPointTime = rand(intval($totalMinutes * 0.2), intval($totalMinutes * 0.8));
        $lowPointTime = rand(intval($totalMinutes * 0.2), intval($totalMinutes * 0.8));
        
        while (abs($highPointTime - $lowPointTime) < $totalMinutes * 0.1) {
            $lowPointTime = rand(intval($totalMinutes * 0.2), intval($totalMinutes * 0.8));
        }
        
        $keyPoints[] = ['time' => $highPointTime, 'price' => $this->highPrice];
        $keyPoints[] = ['time' => $lowPointTime, 'price' => $this->lowPrice];
        
        // 添加其他随机关键点
        for ($i = 0; $i < $numKeyPoints - 2; $i++) {
            $time = rand(1, $totalMinutes - 1);
            $price = $this->lowPrice + ($this->highPrice - $this->lowPrice) * (0.2 + 0.6 * rand(0, 100) / 100);
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
            $t2 = $t * $t;
            $t3 = $t2 * $t;
            
            $h00 = 2 * $t3 - 3 * $t2 + 1;
            $h01 = -2 * $t3 + 3 * $t2;
            
            $price = $h00 * $prevPoint['price'] + $h01 * $nextPoint['price'];
            
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

}
