<?php

namespace Internal\Market\Actions;

use App\Exceptions\LogicException;
use DateInterval;
use DateTime;
use DateTimeZone;

class GenerateKline
{
    /* -------- 基础参数 -------- */
    private int $precision = 4;          // 小数位
    private int $pipFactor = 10000;      // 10^precision
    private int $startTs;
    private int $endTs;
    private \DateTime $startDt;
    private int $P0;                     // 起始价(pips)
    private int $P1;                     // 终止价(pips)
    private int $Lo;                     // 全局最低边界(pips)
    private int $Hi;                     // 全局最高边界(pips)
    private ?int $seed;

    /* -------- 过程参数（可覆盖） -------- */
    private float $sineAmpBandPct     = 0.08;  // 正弦摆动幅度相对带宽
    private float $noisePhi           = 0.95;  // OU 衰减
    private float $noiseSigmaBandPct  = 0.005; // OU 强度相对带宽
    private float $wiggleFracOfRange  = 0.30;  // 影线占“基础波动”的比例
    private float $maxWickPctOfPrice  = 0.006; // 影线不超过价格的 x%
    private int   $maxWickPips        = 45;    // 影线绝对上限（pips），0=不启用
    private bool  $useExtremeWicks    = false; // 是否埋一次极端影线
    private int   $monthCyclesMin     = 2;
    private int   $monthCyclesMax     = 6;
    private bool  $forceLastCloseToP1 = true;  // 最后一根分钟收盘贴合终价

    /* -------- Sink 管理：一个 interval 可有多个 sink --------
       $sinks[interval] = [
         [ 'type'=>'influx_csv', 'fp'=>resource, 'measurement'=>string, 'tagKeys'=>[], 'tagValues'=>[] ],
         [ 'type'=>'cb', 'fn'=>\Closure, 'meta'=>array ],
         ...
       ]
    --------------------------------------------------------- */
    private array $sinks = [];

    /* -------- 1m 输出（可选） -------- */
    private bool $emit1m = false;
    /** @var \Closure|null */
    private ?\Closure $on1m = null;

    /* -------- 固定步长聚合状态 -------- */
    private array $fixedSpecs = [
        '5m'  => 300,
        '15m' => 900,
        '30m' => 1800,
        '1h'  => 3600,
        '1d'  => 86400,
        '1w'  => 604800,
    ];
    private array $fixedAggs = []; // name=>['step','anchor','bucket','idx']

    /* -------- 自然月聚合状态 -------- */
    private ?array $monthBucket = null;
    private ?\DateTime $monthCursorStart = null;
    private ?\DateTime $monthCursorEnd   = null;

    /* ========================== 构造 ========================== */
    public function __construct(
        string $startTime,
        string $endTime,
        $high, $low, $startPrice, $endPrice,
        ?int $seed = null,
        array $opts = [] // 可覆盖上述过程参数（见下）
    ) {
        $this->startTs = strtotime($startTime);
        $this->endTs   = strtotime($endTime);
        if ($this->endTs <= $this->startTs) {
            throw new \InvalidArgumentException('endTime must be greater than startTime');
        }
        $this->startDt = new \DateTime($startTime, new \DateTimeZone('UTC'));
        $this->seed = $seed;
        if ($seed !== null) mt_srand($seed);

        $this->P0 = $this->toPips($startPrice);
        $this->P1 = $this->toPips($endPrice);
        $this->Lo = $this->toPips($low);
        $this->Hi = $this->toPips($high);

        // 边界自洽
        if ($this->Lo > min($this->P0, $this->P1)) $this->Lo = min($this->Lo, min($this->P0, $this->P1));
        if ($this->Hi < max($this->P0, $this->P1)) $this->Hi = max($this->Hi, max($this->P0, $this->P1));
        if ($this->Hi <= $this->Lo) {
            $mid = intdiv($this->P0 + $this->P1, 2);
            $this->Lo = $mid - 50;  // 0.0050
            $this->Hi = $mid + 50;
        }

        // 覆盖过程参数（可传：sineAmpBandPct, noisePhi, noiseSigmaBandPct, wiggleFracOfRange,
        // maxWickPctOfPrice, maxWickPips, useExtremeWicks, monthCyclesMin, monthCyclesMax,
        // forceLastCloseToP1, precision）
        foreach ([
            'sineAmpBandPct','noisePhi','noiseSigmaBandPct','wiggleFracOfRange',
            'maxWickPctOfPrice','maxWickPips','useExtremeWicks',
            'monthCyclesMin','monthCyclesMax','forceLastCloseToP1','precision'
        ] as $k) {
            if (array_key_exists($k, $opts) && $opts[$k] !== null) {
                $this->$k = $opts[$k];
            }
        }
        $this->pipFactor = 10 ** $this->precision;

        // 初始化固定聚合器槽位
        foreach ($this->fixedSpecs as $name => $sec) {
            $this->fixedAggs[$name] = [
                'step'   => $sec,
                'anchor' => $this->startTs,
                'bucket' => null,
                'idx'    => null,
            ];
        }
        // 初始化自然月游标
        $this->monthCursorStart = (clone $this->startDt);
        $this->monthCursorEnd   = (clone $this->startDt);
        $this->monthCursorEnd->add(new \DateInterval('P1M'));
    }

    /* ========================== Sink 注册 ========================== */

    /** 开启 1m 输出（可传回调） */
    public function enable1mOutput(bool $enable = true, ?callable $on1m = null): self {
        $this->emit1m = $enable;
        $this->on1m = $on1m ? \Closure::fromCallable($on1m) : null;
        return $this;
    }

    /**
     * 注册 Influx 注解 CSV sink
     * @param string $interval   例如 '5m'
     * @param string $filepath   输出路径
     * @param string $measurement measurement 列的固定值（如 'kline'）
     * @param array  $tags       关联数组：['symbol'=>'NBUSDT','interval'=>'5m', ...]
     */
    public function addInfluxCsvSink(string $interval, string $filepath, string $measurement, array $tags): self {
        $this->assertInterval($interval);
        $dir = \dirname($filepath);
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $fp = fopen($filepath, 'w');
        if (!$fp) throw new \RuntimeException("Cannot open $filepath");

        // 构造 #datatype 行：measurement, tag..., double*4, long, dateTime:RFC3339
        $dt = '#datatype measurement';
        $tagKeys = array_keys($tags);
        foreach ($tagKeys as $_) $dt .= ',tag';
        $dt .= ',string,string,string,string,long,dateTime:RFC3339';
        fwrite($fp, $dt . "\n");

        // 头行
        $headers = array_merge(['name'], $tagKeys, ['o','h','l','c','v','time']);
        fputcsv($fp, $headers);

        if (!isset($this->sinks[$interval])) $this->sinks[$interval] = [];
        $this->sinks[$interval][] = [
            'type'        => 'influx_csv',
            'fp'          => $fp,
            'measurement' => $measurement,
            'tagKeys'     => $tagKeys,
            'tagValues'   => array_values($tags),
        ];
        return $this;
    }

    /** 注册回调 Sink（同一 interval 可并存多个 sink） */
    public function addCallbackSink(string $interval, callable $fn, array $meta = []): self {
        $this->assertInterval($interval);
        $cb = \Closure::fromCallable($fn);
        if (!isset($this->sinks[$interval])) $this->sinks[$interval] = [];
        $this->sinks[$interval][] = ['type' => 'cb', 'fn' => $cb, 'meta' => $meta];
        return $this;
    }

    /** 关闭所有 CSV 句柄 */
    public function close(): void {
        foreach ($this->sinks as $list) {
            foreach ($list as $sink) {
                if (($sink['type'] ?? '') === 'influx_csv'
                    && isset($sink['fp']) && is_resource($sink['fp'])) {
                    fclose($sink['fp']);
                }
            }
        }
        $this->sinks = [];
    }

    /* ========================== 运行主逻辑 ========================== */

    public function run(): void {
        $totalMins = intdiv($this->endTs - $this->startTs, 60);
        if ($totalMins < 1) throw new \RuntimeException('Range must be at least 1 minute');

        // 启用的周期（有任意 sink 即视为启用）
        $enabledFixed = array_values(array_intersect(array_keys($this->fixedSpecs), array_keys($this->sinks)));
        $hasMonthSink = !empty($this->sinks['1M']);
        $has1mSink    = $this->emit1m && !empty($this->sinks['1m']);

        // 过程参数
        $band    = max(1, $this->Hi - $this->Lo);
        $ampBase = max(1, (int)round($band * $this->sineAmpBandPct));
        $phi     = $this->noisePhi;
        $sigma   = max(1.0, $band * $this->noiseSigmaBandPct);
        $v       = 0.0;

        $days   = ($this->endTs - $this->startTs) / 86400.0;
        $cycles = (int)floor($days / 30.0);
        $cycles = max($this->monthCyclesMin, min($this->monthCyclesMax, $cycles));
        $phase1 = $this->randFloat(0, 2 * M_PI);
        $phase2 = $this->randFloat(0, 2 * M_PI);

        // 极端影线（可选）
        $iHighWick = $iLowWick = -1;
        if ($this->useExtremeWicks) {
            $iHighWick = max(1, min($totalMins - 2, (int)round($totalMins * 0.62 + mt_rand(-120, 120))));
            $iLowWick  = max(1, min($totalMins - 2, (int)round($totalMins * 0.33 + mt_rand(-120, 120))));
        }

        $prevClose = $this->P0;

        for ($i = 0; $i < $totalMins; $i++) {
            $ts   = $this->startTs + $i * 60;
            $prog = $i / max(1, $totalMins);

            // 线性趋势 + 多周期正弦
            $base   = (int)round((1 - $prog) * $this->P0 + $prog * $this->P1);
            $sinMix = sin(2 * M_PI * ($cycles * $prog) + $phase1)
                    + 0.5 * sin(2 * M_PI * (($cycles / 2) * $prog) + $phase2);
            $ampVar = (0.6 + 0.8 * (0.5 + 0.5 * sin(2 * M_PI * ($prog + 0.27))));
            $A      = (int)round($ampBase * $ampVar);

            // OU 噪声
            $v      = $phi * $v + $this->randn() * $sigma;
            $target = (int)round($base + $A * $sinMix + $v);
            $target = $this->reflect($target, $this->Lo, $this->Hi);

            // 1m OHLCV
            $open  = $prevClose;
            $close = $target;
            if ($this->forceLastCloseToP1 && $i === $totalMins - 1) $close = $this->P1;

            // 基础波动估计
            $range  = max(1, (int)round(abs($close - $open) + 0.10 * $ampBase + abs($v) * 0.10));
            $wiggle = max(1, (int)round($range * $this->wiggleFracOfRange));

            // 影线上限（相对 & 绝对）
            $upper = max($open, $close);
            $lower = min($open, $close);
            $capByPct = (int)max(1, round($upper * $this->maxWickPctOfPrice));
            $wickCap  = min($wiggle, $capByPct);
            if ($this->maxWickPips > 0) $wickCap = min($wickCap, $this->maxWickPips);

            $high = min($this->Hi, $upper + mt_rand(0, $wickCap));
            $low  = max($this->Lo, $lower - mt_rand(0, $wickCap));

            if ($i === $iHighWick) $high = $this->Hi;
            if ($i === $iLowWick)  $low  = $this->Lo;
            if ($low > $high) { $low = min($open, $close); $high = max($open, $close); }

            // 成交量
            $ret = abs($close - $open) / max(1, $open);
            $uShape = 1 + 0.2 * sin(2 * M_PI * fmod($ts / 3600, 24) / 24.0 * 2);
            $vol = (int)max(1, round(100 * (1 + $ret * 2000) * $uShape * (0.7 + mt_rand(0, 100) / 100.0)));

            // 1m 输出（可选）
            if ($has1mSink) {
                $bar1m = [
                    'tl' => $ts, 'o' => $this->fmt($open), 'h' => $this->fmt($high),
                    'l' => $this->fmt($low), 'c' => $this->fmt($close), 'v' => $vol
                ];
                $this->emit('1m', $bar1m);
                if ($this->on1m) { ($this->on1m)($bar1m); }
            }

            // 固定步长聚合
            foreach ($enabledFixed as $name) {
                $this->ingestFixed($name, $ts, $open, $high, $low, $close, $vol);
            }

            // 自然月聚合
            if ($hasMonthSink) {
                $this->ingestMonth($ts, $open, $high, $low, $close, $vol);
            }

            $prevClose = $close;
        }

        // 冲刷最后桶
        foreach ($enabledFixed as $name) $this->flushFixed($name);
        if ($hasMonthSink) $this->flushMonth();
    }

    /* ========================== 内部工具 ========================== */

    private function toPips($price): int {
        if (is_string($price)) $price = (float)$price;
        return (int)round($price * $this->pipFactor);
    }
    private function fmt(int $pips): string {
        return number_format($pips / $this->pipFactor, $this->precision, '.', '');
    }
    private function reflect(int $x, int $lo, int $hi): int {
        if ($x < $lo) { $d = $lo - $x; $x = $lo + $d; }
        elseif ($x > $hi) { $d = $x - $hi; $x = $hi - $d; }
        return max($lo, min($hi, $x));
    }
    private function randFloat(float $min, float $max): float {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }
    private function randn(): float {
        $u = max(1e-12, mt_rand() / mt_getrandmax());
        $v = max(1e-12, mt_rand() / mt_getrandmax());
        return sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }
    private function assertInterval(string $interval): void {
        static $allowed = ['1m','5m','15m','30m','1h','1d','1w','1month','1M'];
        if (!in_array($interval, $allowed, true)) {
            throw new \InvalidArgumentException("Unsupported interval: $interval");
        }
    }

    /* -------- 发射到所有 sink（某 interval 可有多个） -------- */
    private function emit(string $interval, array $bar): void {
        $sinks = $this->sinks[$interval] ?? [];
        if (!$sinks) return;

        foreach ($sinks as $sink) {
            $type = $sink['type'] ?? '';
            if ($type === 'influx_csv') {
                $row = [];
                $row[] = $sink['measurement'];                        // name
                foreach ($sink['tagValues'] as $v) $row[] = (string)$v; // tag 列
                $row[] = $bar['o']; $row[] = $bar['h']; $row[] = $bar['l']; $row[] = $bar['c'];
                $row[] = (string)$bar['v'];
                $row[] = gmdate('Y-m-d\TH:i:s\Z', $bar['tl']);         // RFC3339 Z
                fputcsv($sink['fp'], $row);
            } elseif ($type === 'cb') {
                /** @var \Closure $fn */
                $fn = $sink['fn'];
                $meta = $sink['meta'] ?? [];
                // 兼容两种签名：fn(array $bar) 或 fn(array $bar, array $meta)
                $ref = new \ReflectionFunction($fn);
                if ($ref->getNumberOfParameters() >= 2) {
                    $fn($bar, $meta);
                } else {
                    $fn($bar);
                }
            }
        }
    }

    /* -------- 固定步长聚合 -------- */
    private function idxFor(string $name, int $ts): int {
        $ag = $this->fixedAggs[$name];
        return intdiv($ts - $ag['anchor'], $ag['step']);
    }
    private function flushFixed(string $name): void {
        $ag =& $this->fixedAggs[$name];
        if ($ag['bucket'] === null) return;
        $b = $ag['bucket'];
        $this->emit($name, [
            'tl' => $b['tl'],
            'o' => $this->fmt($b['o']),
            'h' => $this->fmt($b['h']),
            'l' => $this->fmt($b['l']),
            'c' => $this->fmt($b['c']),
            'v' => $b['v'],
        ]);
        $ag['bucket'] = null;
        $ag['idx'] = null;
    }
    private function ingestFixed(string $name, int $ts, int $o, int $h, int $l, int $c, int $v): void {
        $ag =& $this->fixedAggs[$name];
        $idx = $this->idxFor($name, $ts);
        if ($ag['bucket'] === null || $ag['idx'] !== $idx) {
            if ($ag['bucket'] !== null) $this->flushFixed($name);
            $start = $ag['anchor'] + $idx * $ag['step'];
            $ag['bucket'] = ['tl'=>$start,'o'=>$o,'h'=>$h,'l'=>$l,'c'=>$c,'v'=>$v];
            $ag['idx'] = $idx;
        } else {
            $b =& $ag['bucket'];
            $b['h'] = max($b['h'], $h);
            $b['l'] = min($b['l'], $l);
            $b['c'] = $c;
            $b['v'] += $v;
        }
    }

    /* -------- 自然月聚合 -------- */
    private function flushMonth(): void {
        if ($this->monthBucket === null) return;
        $b = $this->monthBucket;
        $this->emit('1M', [
            'tl'=>$b['tl'],
            'o'=>$this->fmt($b['o']),
            'h'=>$this->fmt($b['h']),
            'l'=>$this->fmt($b['l']),
            'c'=>$this->fmt($b['c']),
            'v'=>$b['v'],
        ]);
        $this->monthBucket = null;
    }
    private function ingestMonth(int $ts, int $o, int $h, int $l, int $c, int $v): void {
        $t = (new \DateTime('@'.$ts))->setTimezone(new \DateTimeZone('UTC'));
        while ($t >= $this->monthCursorEnd) {
            $this->flushMonth();
            $this->monthCursorStart = (clone $this->monthCursorEnd);
            $this->monthCursorEnd->add(new \DateInterval('P1M'));
        }
        $startUnix = $this->monthCursorStart->getTimestamp();
        if ($this->monthBucket === null) {
            $this->monthBucket = ['tl'=>$startUnix,'o'=>$o,'h'=>$h,'l'=>$l,'c'=>$c,'v'=>$v];
        } else {
            $b =& $this->monthBucket;
            $b['h'] = max($b['h'], $h);
            $b['l'] = min($b['l'], $l);
            $b['c'] = $c;
            $b['v'] += $v;
        }
    }
}


// class GenerateKline
// {
//     /* ---------- 基本参数 ---------- */
//     private int $precision = 4;           // 小数位
//     private int $pipFactor = 10000;       // 10^precision
//     private int $startTs;
//     private int $endTs;
//     private int $P0;                      // 起始价 (pips)
//     private int $P1;                      // 终止价 (pips)
//     private int $Lo;                      // 全局最低边界 (pips)
//     private int $Hi;                      // 全局最高边界 (pips)
//     private ?int $seed;
//     private \DateTime $startDt;

//     /* ---------- 下游输出（sink） ---------- */
//     // $sinks[interval] = ['type'=>'csv','fp'=>resource] | ['type'=>'cb','fn'=>callable]
//     private array $sinks = [];

//     /* ---------- 固定步长聚合器状态 ---------- */
//     // $fixedAggs[name] = [
//     //   'step'=>int,'anchor'=>int,'bucket'=>?array,'idx'=>?int
//     // ]
//     private array $fixedAggs = [];
//     private array $fixedSpecs = [
//         '5m'  => 300,
//         '15m' => 900,
//         '30m' => 1800,
//         '1h'  => 3600,
//         '1d'  => 86400,
//         '1w'  => 604800,
//     ];

//     /* ---------- 自然月聚合器状态 ---------- */
//     private ?array $monthBucket = null;              // ['t','o','h','l','c','v']（pips&int）
//     private ?\DateTime $monthCursorStart = null;
//     private ?\DateTime $monthCursorEnd   = null;

//     /* ---------- 选项 ---------- */
//     private bool $emit1m = false;        // 是否输出1m
//     private $on1m = null;      // 1m 回调（可选）

//     /* ======================== 构造与 Sink 注册 ======================== */

//     public function __construct(
//         string $startTime,
//         string $endTime,
//         $high, $low, $startPrice, $endPrice,
//         ?int $seed = null
//     ) {
//         $this->startTs = strtotime($startTime);
//         $this->endTs   = strtotime($endTime);
//         if ($this->endTs <= $this->startTs) {
//             throw new \InvalidArgumentException('endTime must be greater than startTime');
//         }
//         $this->startDt = new \DateTime($startTime, new \DateTimeZone('UTC'));
//         $this->seed = $seed;
//         if ($seed !== null) mt_srand($seed);

//         $this->P0 = $this->toPips($startPrice);
//         $this->P1 = $this->toPips($endPrice);
//         $this->Lo = $this->toPips($low);
//         $this->Hi = $this->toPips($high);

//         // 修正边界使之覆盖起/终价
//         if ($this->Lo > min($this->P0, $this->P1)) $this->Lo = min($this->Lo, min($this->P0, $this->P1));
//         if ($this->Hi < max($this->P0, $this->P1)) $this->Hi = max($this->Hi, max($this->P0, $this->P1));
//         if ($this->Hi <= $this->Lo) {
//             $mid = intdiv($this->P0 + $this->P1, 2);
//             $this->Lo = $mid - 50;  // 0.0050
//             $this->Hi = $mid + 50;
//         }

//         // 初始化固定步长聚合器状态（仅创建，不写出）
//         foreach ($this->fixedSpecs as $name => $sec) {
//             $this->fixedAggs[$name] = [
//                 'step'   => $sec,
//                 'anchor' => $this->startTs,
//                 'bucket' => null,
//                 'idx'    => null,
//             ];
//         }

//         // 初始化自然月游标（仅在启用 month sink 时真正使用）
//         $this->monthCursorStart = (clone $this->startDt);
//         $this->monthCursorEnd   = (clone $this->startDt);
//         $this->monthCursorEnd->add(new \DateInterval('P1M'));
//     }

//     /** 开启 1m 输出（可选：提供回调；否则仅 CSV sink 生效） */
//     public function enable1mOutput(bool $enable = true, ?callable $on1m = null): self {
//         $this->emit1m = $enable;
//         $this->on1m = $on1m;
//         return $this;
//     }

//     /** 注册一个 CSV Sink（会写入 header: t,o,h,l,c,v） */
//     public function addCsvSink(string $interval, string $filepath, bool $append = false): self {
//         $this->assertInterval($interval);
//         $dir = \dirname($filepath);
//         if (!is_dir($dir)) mkdir($dir, 0777, true);

//         $mode = $append ? 'a' : 'w';
//         $fp = fopen($filepath, $mode);
//         if (!$fp) throw new \RuntimeException("Cannot open $filepath");
//         if (!$append) {
//             fputcsv($fp, ['t','o','h','l','c','v']);
//         }
//         $this->sinks[$interval] = ['type' => 'csv', 'fp' => $fp];
//         return $this;
//     }

//     /** 注册一个回调 Sink：fn(array $bar) */
//     public function addCallbackSink(string $interval, callable $fn): self {
//         $this->assertInterval($interval);
//         $this->sinks[$interval] = ['type' => 'cb', 'fn' => $fn];
//         return $this;
//     }

//     /* ============================ 运行主逻辑 ============================ */

//     /** 生成并写出。注意：默认不输出 1m，除非 enable1mOutput(true) 且配置了 1m sink/回调 */
//     public function run(): void {
//         $totalMins = intdiv($this->endTs - $this->startTs, 60);
//         if ($totalMins < 1) throw new \RuntimeException('Range must be at least 1 minute');

//         // 确定启用了哪些聚合 Sink
//         $enabledFixed = array_intersect(array_keys($this->sinks), array_keys($this->fixedSpecs));
//         $hasMonthSink = isset($this->sinks['1month']);
//         $has1mSink    = $this->emit1m && (isset($this->sinks['1m']) || $this->on1m);

//         // 价格过程参数
//         $band    = max(1, $this->Hi - $this->Lo);
//         $ampBase = max(1, (int)round($band * 0.08)); // 基础摆动 ~8% 带宽
//         $phi     = 0.95;                              // OU 衰减
//         $sigma   = max(1, (int)round($band * 0.005)); // OU 强度 ~0.5% 带宽
//         $v       = 0.0;

//         $days   = ($this->endTs - $this->startTs) / 86400.0;
//         $cycles = max(2, min(6, (int)floor($days / 30))); // 大约每月一个周期，上限6
//         $phase1 = $this->randFloat(0, 2 * M_PI);
//         $phase2 = $this->randFloat(0, 2 * M_PI);

//         // 随机影线触发分钟
//         $iHighWick = (int)round($totalMins * 0.62 + mt_rand(-120, 120));
//         $iLowWick  = (int)round($totalMins * 0.33 + mt_rand(-120, 120));
//         $iHighWick = max(1, min($totalMins - 2, $iHighWick));
//         $iLowWick  = max(1, min($totalMins - 2, $iLowWick));

//         $prevClose = $this->P0;

//         for ($i = 0; $i < $totalMins; $i++) {
//             $ts   = $this->startTs + $i * 60;
//             $prog = $i / max(1, $totalMins);

//             // 线性趋势 + 多周期正弦
//             $base   = (int)round((1 - $prog) * $this->P0 + $prog * $this->P1);
//             $sinMix = sin(2 * M_PI * ($cycles * $prog) + $phase1)
//                     + 0.5 * sin(2 * M_PI * (($cycles / 2) * $prog) + $phase2);
//             $ampVar = (0.6 + 0.8 * (0.5 + 0.5 * sin(2 * M_PI * ($prog + 0.27))));
//             $A      = (int)round($ampBase * $ampVar);

//             // OU 噪声
//             $v      = $phi * $v + $this->randn() * $sigma;
//             $target = (int)round($base + $A * $sinMix + $v);
//             $target = $this->reflect($target, $this->Lo, $this->Hi);

//             // 生成 1m OHLCV
//             $open  = $prevClose;
//             $close = $target;
//             if ($i === $totalMins - 1) $close = $this->P1; // 末根贴合目标收盘

//             $range  = max(1, (int)round(abs($close - $open) + 0.10 * $ampBase + abs($v) * 0.10));
//             $wiggle = max(1, (int)round($range * 0.4));
//             $high   = min($this->Hi, max($open, $close) + mt_rand(0, $wiggle));
//             $low    = max($this->Lo, min($open, $close) - mt_rand(0, $wiggle));
//             if ($i === $iHighWick) $high = $this->Hi;
//             if ($i === $iLowWick)  $low  = $this->Lo;
//             if ($low > $high) { $low = min($open, $close); $high = max($open, $close); }

//             $ret    = abs($close - $open) / max(1, $open);
//             $uShape = 1 + 0.2 * sin(2 * M_PI * fmod($ts / 3600, 24) / 24.0 * 2);
//             $vol    = (int)max(1, round(100 * (1 + $ret * 2000) * $uShape * (0.7 + mt_rand(0, 100) / 100.0)));

//             // 1m 输出（可选）
//             if ($has1mSink) {
//                 $bar1m = [
//                     't' => $ts, 'o' => $this->fmt($open), 'h' => $this->fmt($high),
//                     'l' => $this->fmt($low), 'c' => $this->fmt($close), 'v' => $vol
//                 ];
//                 $this->emit('1m', $bar1m);
//                 if (is_callable($this->on1m)) { \call_user_func($this->on1m, $bar1m); }
//             }

//             // 固定步长聚合
//             foreach ($enabledFixed as $name) {
//                 $this->ingestFixed($name, $ts, $open, $high, $low, $close, $vol);
//             }

//             // 自然月聚合
//             if ($hasMonthSink) {
//                 $this->ingestMonth($ts, $open, $high, $low, $close, $vol);
//             }

//             $prevClose = $close;

//             // 偶尔 flush stdout 缓存（可忽略）
//             if (($i & 8191) === 8191 && defined('STDOUT')) fflush(STDOUT);
//         }

//         // 收尾：冲刷最后桶
//         foreach ($enabledFixed as $name) $this->flushFixed($name);
//         if ($hasMonthSink) $this->flushMonth();
//     }

//     /** 关闭所有文件句柄（CSV sink） */
//     public function close(): void {
//         foreach ($this->sinks as $name => $sink) {
//             if (($sink['type'] ?? '') === 'csv' && isset($sink['fp']) && is_resource($sink['fp'])) {
//                 fclose($sink['fp']);
//             }
//         }
//         $this->sinks = [];
//     }

//     /* ============================ 内部工具 ============================ */

//     private function toPips($x): int {
//         if (is_string($x)) $x = (float)$x;
//         return (int)round($x * $this->pipFactor);
//     }
//     private function fmt(int $p): string {
//         return number_format($p / $this->pipFactor, $this->precision, '.', '');
//     }
//     private function reflect(int $x, int $lo, int $hi): int {
//         if ($x < $lo) { $d = $lo - $x; $x = $lo + $d; }
//         elseif ($x > $hi) { $d = $x - $hi; $x = $hi - $d; }
//         return max($lo, min($hi, $x));
//     }
//     private function randFloat(float $a, float $b): float {
//         return $a + (mt_rand() / mt_getrandmax()) * ($b - $a);
//     }
//     private function randn(): float {
//         $u = max(1e-12, mt_rand()/mt_getrandmax());
//         $v = max(1e-12, mt_rand()/mt_getrandmax());
//         return sqrt(-2*log($u)) * cos(2*M_PI*$v);
//     }
//     private function assertInterval(string $interval): void {
//         static $allowed = ['1m','5m','15m','30m','1h','1d','1w','1month','1M'];
//         if (!in_array($interval, $allowed, true)) {
//             throw new \InvalidArgumentException("Unsupported interval: $interval");
//         }
//     }

//     /* ---------- 发射（写出） ---------- */
//     private function emit(string $interval, array $bar): void {
//         if (!isset($this->sinks[$interval])) return;
//         $sink = $this->sinks[$interval];
//         if ($sink['type'] === 'csv') {
//             fputcsv($sink['fp'], [$bar['t'],$bar['o'],$bar['h'],$bar['l'],$bar['c'],$bar['v']]);
//         } elseif ($sink['type'] === 'cb') {
//             \call_user_func($sink['fn'], $bar);
//         }
//     }

//     /* ---------- 固定步长聚合 ---------- */
//     private function idxFor(string $name, int $ts): int {
//         $ag = $this->fixedAggs[$name];
//         return intdiv($ts - $ag['anchor'], $ag['step']);
//     }
//     private function flushFixed(string $name): void {
//         $ag = &$this->fixedAggs[$name];
//         if ($ag['bucket'] === null) return;
//         $b = $ag['bucket'];
//         $this->emit($name, [
//             't' => $b['t'],
//             'o' => $this->fmt($b['o']),
//             'h' => $this->fmt($b['h']),
//             'l' => $this->fmt($b['l']),
//             'c' => $this->fmt($b['c']),
//             'v' => $b['v'],
//         ]);
//         $ag['bucket'] = null;
//         $ag['idx'] = null;
//     }
//     private function ingestFixed(string $name, int $ts, int $o, int $h, int $l, int $c, int $v): void {
//         $ag = &$this->fixedAggs[$name];
//         $idx = $this->idxFor($name, $ts);
//         if ($ag['bucket'] === null || $ag['idx'] !== $idx) {
//             if ($ag['bucket'] !== null) $this->flushFixed($name);
//             $start = $ag['anchor'] + $idx * $ag['step'];
//             $ag['bucket'] = ['t'=>$start,'o'=>$o,'h'=>$h,'l'=>$l,'c'=>$c,'v'=>$v];
//             $ag['idx'] = $idx;
//         } else {
//             $b =& $ag['bucket'];
//             $b['h'] = max($b['h'], $h);
//             $b['l'] = min($b['l'], $l);
//             $b['c'] = $c;
//             $b['v'] += $v;
//         }
//     }

//     /* ---------- 自然月聚合 ---------- */
//     private function flushMonth(): void {
//         if ($this->monthBucket === null) return;
//         $b = $this->monthBucket;
//         $this->emit('1month', [
//             't'=>$b['t'],
//             'o'=>$this->fmt($b['o']),
//             'h'=>$this->fmt($b['h']),
//             'l'=>$this->fmt($b['l']),
//             'c'=>$this->fmt($b['c']),
//             'v'=>$b['v'],
//         ]);
//         $this->monthBucket = null;
//     }
//     private function ingestMonth(int $ts, int $o, int $h, int $l, int $c, int $v): void {
//         // 推进到包含 ts 的自然月
//         $t = (new \DateTime('@'.$ts))->setTimezone(new \DateTimeZone('UTC'));
//         while ($t >= $this->monthCursorEnd) {
//             $this->flushMonth();
//             $this->monthCursorStart = (clone $this->monthCursorEnd);
//             $this->monthCursorEnd->add(new \DateInterval('P1M'));
//         }
//         $startUnix = $this->monthCursorStart->getTimestamp();
//         if ($this->monthBucket === null) {
//             $this->monthBucket = ['t'=>$startUnix,'o'=>$o,'h'=>$h,'l'=>$l,'c'=>$c,'v'=>$v];
//         } else {
//             $b =& $this->monthBucket;
//             $b['h'] = max($b['h'], $h);
//             $b['l'] = min($b['l'], $l);
//             $b['c'] = $c;
//             $b['v'] += $v;
//         }
//     }
// }