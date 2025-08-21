<?php

namespace App\Internal\Tools\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * 金融数据模拟器类
 * 用于生成平滑的价格序列和聚合K线数据
 */
class FinancialDataSimulator {

	private static int       $startTime;
	protected static ?float  $maxStepChange;
	protected static ?float  $theta;
	protected static ?float  $sigma;
	protected static ?int    $scale;
	protected static ?string $interval;

	/**
	 * 运行金融数据模拟器以生成指定参数的K线数据
	 *
	 * 参数：
	 *
	 * @param int    $startTime     开始时间戳
	 * @param float  $start         开始价格
	 * @param float  $end           结束价格
	 * @param int    $duration      模拟的总持续时间（以秒为单位）
	 * @param float  $min           价格的最小值
	 * @param float  $max           价格的最大值
	 * @param float  $maxStepChange 最大步长变化，默认值为配置中的max_step_change
	 * @param float  $theta         θ参数，默认值为配置中的theta
	 * @param float  $sigma         σ参数，默认值为配置中的sigma
	 * @param int    $scale         缩放因子，默认值为配置中的scale
	 * @param string $interval      时间间隔字符串（如"1h"），默认值为配置中的interval
	 *
	 * @return array 生成的K线数据数组
	 */
	public static function run(
		int    $startTime,
		float  $start,
		float  $end,
		int    $duration,
		float  $min,
		float  $max,
		float  $maxStepChange = 0.01,
		float  $theta = 0.01,
		float  $sigma = 0.003,
		int    $scale = 5,
		string $interval = "1h",
		bool   $origin = false
	): array
	{
		// 设置总持续时间，默认值为参数中的duration
		self::$startTime = $startTime;
		// 设置最大步长变化，默认值为配置中的max_step_change
		self::$maxStepChange = $maxStepChange ?: config('kline.max_step_change', 0.01);
		// 设置θ参数，默认值为配置中的theta
		self::$theta = $theta ?: config('kline.theta', 0.01);
		// 设置σ参数，默认值为配置中的sigma
		self::$sigma = $sigma ?: config('kline.sigma', 0.003);
		// 设置缩放因子，默认值为配置中的scale
		self::$scale = $scale ?: config('kline.scale', 5);
		// 设置时间间隔字符串，默认值为配置中的interval
		self::$interval = $interval ?: config('kline.interval', '1h');

		// 生成每秒价格数据
		$data = self::generateSmoothPriceSeries($start, $end, $duration, $min, $max);

		// 输入你想聚合的周期（如 "1m"、"5m"、"15m"、"30m"）
		$intervalSeconds = self::parseIntervalToSeconds();

		$candles = self::aggregateCandles($data, $intervalSeconds);
		// 聚合为K线数据
		if ($origin) {
			return [
				'prices'  => $data,
				'candles' => $candles,
			];
		}
		return $candles;
	}

	/**
	 * 自动调整步长范围，以适应给定的起始值和结束值以及持续时间
	 *
	 * 此函数的目的是根据指定的起始值、结束值和持续时间来计算一个合适的步长
	 * 它确保了在给定的时间内，通过逐步增加从起始值到结束值的变化，可以平滑地达到最终目标
	 * 这在动画、渐变效果或任何需要平滑过渡的场景中特别有用
	 *
	 * @param float $start          起始值，表示过渡开始时的值
	 * @param float $end            结束值，表示过渡结束时的目标值
	 * @param int   $duration       持续时间，表示从起始值过渡到结束值所需的时间单位
	 * @param float $defaultMaxStep 默认最大步长，表示在任何情况下都不应超过的步长上限
	 * @param float $buffer         缓冲系数，默认为1.1，用于调整计算出的步长，以确保过渡更加平滑或快速
	 *
	 * @return float 返回根据给定参数计算出的步长，确保在过渡过程中既不会过快也不会过慢
	 */
	public static function autoAdjustStepRange(float $start, float $end, int $duration, float $defaultMaxStep, float $buffer = 1.1): float
	{
		// 计算基于起始值和结束值之间的绝对差值除以持续时间得到的必要步长
		$required = abs($end - $start) / $duration;
		// 返回默认最大步长和计算出的必要步长乘以缓冲系数之间的较大值
		// 这样做是为了确保步长既满足过渡需求，又不会无故地超过预设的最大值
		return max($defaultMaxStep, $required * $buffer);
	}

	/**
	 * 生成标准正态分布的随机数
	 *
	 * 本函数使用Box-Muller变换算法，将两个独立的[0,1)区间上的均匀分布随机数
	 * 转换为两个独立的标准正态分布随机数
	 *
	 * @return float 返回一个标准正态分布的随机数
	 */
	private static function generateNormalRandom(): float
	{
		// 初始化u和v为0，确保它们不会与后续生成的随机数混淆
		$u = 0;
		$v = 0;

		// 生成(0,1)区间上的均匀分布随机数u
		// 避免生成0，因为对数函数的定义域不包括0
		while ($u === 0) {
			$u = mt_rand() / mt_getrandmax();
		}

		// 生成(0,1)区间上的均匀分布随机数v
		// 同样避免生成0，以确保可以进行三角函数计算
		while ($v === 0) {
			$v = mt_rand() / mt_getrandmax();
		}

		// 使用Box-Muller变换公式生成标准正态分布的随机数
		// 公式：sqrt(-2.0 * log(u)) * cos(2.0 * PI * v)
		// 其中，sqrt(-2.0 * log(u))是半径，cos(2.0 * PI * v)是角度
		// 通过半径和角度的乘积得到正态分布的随机数
		return sqrt(-2.0 * log($u)) * cos(2.0 * M_PI * $v);
	}

	/**
	 * 生成平滑价格序列
	 * 该函数用于模拟一个平滑的价格变动序列，用于电商商品的价格趋势展示
	 *
	 * @param float $start    起始价格
	 * @param float $end      结束价格
	 * @param int   $duration 价格序列的持续时间（秒）
	 * @param float $min      价格下限
	 * @param float $max      价格上限
	 *
	 * @return array 价格序列数组
	 */
	public static function generateSmoothPriceSeries(float $start, float $end, int $duration, float $min, float $max): array
	{
		// 初始化价格序列数组
		$prices = [];
		// 当前价格初始化为起始价格
		$current = $start;
		// 将起始价格格式化后加入价格序列
		$prices[] = self::formatPrice($start);
		// 减去起始时刻，计算实际变化持续时间
		$duration -= 1;

		// 计算每步最大变化量，以确保价格变化平滑
		$maxStepChange = self::autoAdjustStepRange($start, $end, $duration, self::$maxStepChange);

		// 循环生成价格序列
		for ($t = 0; $t < $duration; $t++) {
			// 计算当前时刻在总持续时间中的进度
			$progress = $t / $duration;

			// 趋势：线性增长
			$trend = $start + ($end - $start) * $progress;
			// 波动幅度：随着时间逐渐减小
			$waveAmplitude = (1 - pow($progress, 2)) * 1.0;
			// 波动：使用正弦函数模拟波动，波动幅度随时间变化
			$wave = sin($progress * M_PI * 6) * $waveAmplitude;
			// 目标价格：趋势价格加上波动价格
			$target = $trend + $wave;

			// 均值回归 + 随机扰动
			$epsilon = self::generateNormalRandom();
			// 计算下一个价格：当前价格向目标价格回归，并加入随机扰动
			$next = $current + self::$theta * ($target - $current) + self::$sigma * $epsilon;

			// 限制每秒最大波动
			$next = max($current - $maxStepChange, min($current + $maxStepChange, $next));
			// 限制价格在指定的上下限之间
			$next = max($min, min($max, $next));

			// 格式化下一个价格并加入价格序列
			$next     = self::formatPrice($next);
			$prices[] = $next;
			// 更新当前价格为下一个价格
			$current = $next;
		}

		// 确保最后一秒等于收盘价
		$prices[$duration] = round($end, self::$scale);
		// 返回价格序列
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
		$timestamp = $timestamp ?: self::$startTime;

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
				'timestamp' => $timestamp*1000,
			];
			$timestamp+=$chunkSize;
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
			$newData[$timestamp++] = (float)$price;
		}
		return $newData;
	}
}