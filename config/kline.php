<?php
return [
	'preview_task_key' => 'Kline:Preview:Task:%s:%s',// 预览任务key
	'preview_key'      => 'Kline:Preview:%s:%s',// 预览key
	'queue_key'        => 'BOT_TASK_%s',// 队列key
	'default_open'     => '1',// 默认开盘价
	'max_step_change'  => env('KLINE_MAX_STEP_CHANGE', 0.00001),// 最大浮动值
	'theta'            => env('KLINE_THETA', 0.01),// 均值回归系数
	'sigma'            => env('KLINE_SIGMA', 0.003),// 随机扰动系数
	'scale'            => env('KLINE_SCALE', 5),// 浮动精度
	'interval'         => env('KLINE_INTERVAL', '1h'),// K线时间间隔
	'over_time'        => env('KLINE_OVER_TIME', 43200),// 超时时间
];