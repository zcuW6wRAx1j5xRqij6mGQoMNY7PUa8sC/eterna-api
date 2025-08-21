<?php

namespace App\Internal\Tools\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class KlineSimulator {

	private static float  $initial;
	private static float  $target;
	private static float  $targetMax;
	private static float  $targetMin;
	private static float  $rateMax;
	private static float  $rateMin;
	private static float  $offset;
	private static int    $scale;
	private static string $interval;

	public static function run(
		float   $initial,
		float   $target,
		float   $targetMax,
		float   $targetMin,
		float   $rateMax,
		float   $rateMin,
		?float  $offset,
		?int    $scale = 5,
		?string $interval = "1h",
		?bool   $origin = false
	)
	{
		self::$offset    = $offset ?: config('kline.offset', 0.05);
		self::$scale     = $scale ?: config('kline.scale', 5);
		self::$interval  = $interval;
		self::$initial   = $initial;
		self::$target    = $target;
		self::$targetMax = $targetMax;
		self::$targetMin = $targetMin;
		self::$rateMax   = $rateMax;
		self::$rateMin   = -abs($rateMin);
		// 生成每秒价格数据
		$prices = self::generateTimeSeries();
		if (is_bool($prices)) {
			return false;
		}

		// 输入你想聚合的周期（如 "1m"、"5m"、"15m"、"30m"）
		$intervalSeconds = self::parseIntervalToSeconds();

		// 聚合K线数据
		$candles = self::aggregateCandles($prices, $intervalSeconds);
		// 聚合为K线数据
		if ($origin) {
			return [
				'prices'  => $prices,
				'candles' => $candles,
			];
		}
		return $candles;
	}

	/**
	 * 生成时间序列数据
	 *
	 * 该方法用于模拟生成一个时间序列的数据数组，主要用于模拟价格或其他指标随时间变化的情况
	 * 它通过在一定范围内随机变化来模拟现实世界中的波动
	 *
	 * @return array|bool 返回生成的时间序列数据数组
	 */
	private static function generateTimeSeries(): array|bool
	{
		// 初始化当前值为初始值
		$current = self::$initial;

		// 生成一个随机的每秒变化量
		$epsilon = pow(10, self::$scale);

		// 初始化停止标志为false
		$stop = false;

		// 计算目标值的最小和最大偏移量
		$offsetMin = bcsub(self::$target, self::$offset, self::$scale);
		$offsetMax = bcadd(self::$target, self::$offset, self::$scale);

		// 将初始值添加到价格数组中
		$prices = [self::$initial];

		$overTime = config('kline.over_time');

		// 计数时间（秒）
		$i = 0;
		// 超过12小时后结束
		$status = false;

		// 开始生成时间序列数据
		do {
			$i++;
			// 生成下一个时间点的值
			$secondValue = bcdiv(rand(bcmul(self::$rateMin, $epsilon, self::$scale), bcmul(self::$rateMax, $epsilon, self::$scale)), $epsilon, self::$scale);

			// 判断状态
			if ($secondValue > 0) {
				// 如果加法操作结果超过最大目标值，则将其限制在最大目标值范围内
				$secondValue = bcadd($current, $secondValue, self::$scale) > self::$targetMax ? bcsub(self::$targetMax, $current, self::$scale) : $secondValue;
			} else {
				// 如果减法操作结果低于最小目标值，则将其限制在最小目标值范围内
				$secondValue = bcadd($current, $secondValue, self::$scale) < self::$targetMin ? bcsub(self::$targetMin, $current, self::$scale) : $secondValue;
			}

			// 根据当前状态计算最终值
			$calcCurrent = bcadd($current, $secondValue, self::$scale);

			// 如果计算出的当前值在目标值的偏移范围内，则将其设置为目标值，并设置停止标志为true
			if ((self::$initial < self::$target && $calcCurrent >= $offsetMin) || (self::$initial > self::$target && $calcCurrent <= $offsetMax)) {
				$calcCurrent = self::$target;
				$stop        = true;
			}

			if ($i >= $overTime) {
				$status = true;
				$stop   = true;
			}
			// 更新当前值
			$current = $calcCurrent;

			// 将当前值添加到价格数组中
			$prices[] = $current;
		} while (!$stop);

		if ($status) {
			return false;
		}
		// 返回生成的时间序列数据
		return $prices;
	}


	/**
	 * 将时间间隔字符串转换为秒数
	 *
	 * 该方法旨在解析一个时间间隔字符串，并将其转换为相应的秒数表示该时间间隔
	 * 时间间隔字符串格式为数字后跟时间单位字符（s, m, h, d），分别表示秒、分、小时和天
	 * 如果时间间隔字符串格式不正确或无法匹配到有效的数字和单位，则默认返回3600秒（1小时）
	 *
	 * @param string $interval 时间间隔字符串，默认为空字符串，表示使用默认时间间隔
	 *
	 * @return int 转换后的秒数表示的时间间隔
	 */
	public static function parseIntervalToSeconds(string $interval = ""): int
	{
		try {
			$interval = $interval ?: self::$interval;
			// 使用正则表达式匹配时间间隔字符串，提取数字和时间单位
			if (preg_match('/^(\d+)([smhd])$/', $interval, $matches)) {
				// 提取匹配到的数字和时间单位
				$num  = (int)$matches[1];
				$unit = $matches[2];
				// 定义时间单位到秒数的乘法因子
				$multipliers = ['s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400];
				// 根据时间单位计算并返回相应的秒数
				return $num * $multipliers[$unit];
			}
			// 如果时间间隔字符串格式不正确，记录错误日志
			Log::error('K线时间范围：匹配不到时间范围');
		} catch (Exception $e) {
			// 如果解析过程中发生异常，记录错误日志包括异常信息
			Log::error('K线时间范围匹配错误：', [sprintf('[%s] %s', $e->getLine(), $e->getFile()), $e->getMessage()]);
		}
		// 默认返回3600秒（1小时）
		return 3600;
	}

	/**
	 * 根据给定的价格数组和时间间隔，聚合K线数据
	 * K线数据包括开盘价、收盘价、最高价和最低价
	 *
	 * @param array $prices            价格数组，代表连续的时间序列价格
	 * @param int   $intervalInSeconds 时间间隔，用于决定每个K线的时间范围
	 * @param int   $timestamp         起始时间戳，默认为当前时间戳
	 *
	 * @return array 返回聚合后的K线数据数组
	 */
	public static function aggregateCandles(array $prices, int $intervalInSeconds, int $timestamp = 0): array
	{
		// 初始化K线数据数组
		$candles = [];
		// 获取价格数组的总数量
		$total = count($prices);
		// 设置每个K线的时间间隔
		$chunkSize = $intervalInSeconds;
		// 上一个K线的收盘价（初始为第一个价格）
		$lastClose = $prices[0] ?? 0;
		// 初始化时间戳
		$timestamp = $timestamp ?: time();

		// 遍历价格数组，按时间间隔聚合K线数据
		for ($i = 0; $i < $total; $i += $chunkSize) {
			// 获取当前时间间隔内的价格切片
			$slice = array_slice($prices, $i, $chunkSize);

			// 如果切片为空，跳过当前循环
			if (count($slice) === 0) {
				continue;
			}

			// 开盘价 = 上一个K线的收盘价（第一根K线除外）
			$open = ($i === 0) ? $slice[0] : $lastClose;
			// 收盘价 = 当前时间间隔内的最后一个价格
			$close = $slice[count($slice) - 1];
			// 最高价 = 当前时间间隔内的最大价格
			$high = max($slice);
			// 最低价 = 当前时间间隔内的最小价格
			$low = min($slice);

			// 更新lastClose为当前K线的收盘价，供下一根K线使用
			$lastClose = $close;

			// 构建当前K线的数据数组，并添加到K线数据数组中
			$candles[] = [
				'open'      => $open,
				'close'     => $close,
				'low'       => $low,
				'high'      => $high,
				'timestamp' => $timestamp * 1000,
			];
			$timestamp += $chunkSize;
		}

		// 返回聚合后的K线数据数组
		return $candles;
	}

	/**
	 * 格式化价格数值
	 *
	 * 该方法用于对价格进行四舍五入操作，确保价格的精度符合类中定义的scale标准
	 * 这对于财务计算非常重要，因为它可以确保所有价格都按照统一的标准进行舍入，
	 * 从而避免可能由于精度问题引起的财务误差
	 *
	 * @param float $value 需要格式化的原始价格数值
	 *
	 * @return float 格式化后的价格数值，保留到类中定义的小数位数
	 */
	private static function formatPrice(float $value): float
	{
		return round($value, self::$scale);
	}

	/**
	 * 格式化任务数据
	 * 该方法的主要目的是根据时间戳对任务数据进行重新组织，以便于后续处理
	 * 它会遍历输入的数据数组，使用每个任务的日期时间（假设为ISO8601格式或Unix时间戳）作为新数组的键
	 * 如果日期时间字段不存在或格式不正确，该任务数据将被跳过
	 *
	 * @param array $data      原始任务数据数组，每个任务数据包括多个字段
	 * @param int   $timestamp 时间戳
	 *
	 * @return array 返回一个新的数组，键为任务的日期时间（Unix时间戳格式），值为原始任务数据
	 */
	public static function formatTaskData(array $data, int $timestamp): array
	{
		$newData = [];
		foreach ($data as $price) {
			$newData[$timestamp++] = $price;
		}
		return $newData;
	}
}