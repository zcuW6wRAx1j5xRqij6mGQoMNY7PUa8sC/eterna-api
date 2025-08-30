<?php

namespace Internal\Market\Services;

use App\Enums\IntervalEnums;
use App\Exceptions\LogicException;
use DateTime;
use DateTimeZone;
use InfluxDB2\WriteType as WriteType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use InfluxDB2\ApiException;
use InfluxDB2\Client;
use InfluxDB2\FluxQueryError;
use InfluxDB2\FluxCsvParserException;
use InfluxDB2\Model\DeletePredicateRequest;
use InfluxDB2\Model\WritePrecision;
use InfluxDB2\Point;
use InfluxDB2\Service\DeleteService;
use InvalidArgumentException;
use RuntimeException;

/** @package Internal\Market\Services */
class InfluxDB
{

    const DefaultKlineSchema = 'kline';

    private $addr = '';
    private $token = '';
    private $bucket = '';
    private $org = '';

    private $client = null;
    private $queryApi = null;

    // private static $ins = null;

    public function __construct(string $bucket)
    {
        $this->addr   = config('influxdb.addr', '');
        $this->token  = config('influxdb.token', '');
        $this->bucket = $bucket;
        $this->org    = config('influxdb.org', '');

        if (!$this->addr || !$this->token || !$this->bucket || !$this->org) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }
        $this->client   = new Client([
            'url'       => $this->addr,
            'token'     => $this->token,
            'bucket'    => $this->bucket,
            'org'       => $this->org,
            'precision' => WritePrecision::MS,
            //'debug'=>true,
        ]);
        $this->queryApi = $this->client->createQueryApi();
    }

    public function health()
    {
        return $this->client->ping();
    }

    // public static function getInstance() {
    //     if (self::$ins === null) {
    //         self::$ins = new self();
    //     }
    //     return self::$ins;
    // }

    public function deleteData(string $symbol)
    {
        $srv       = $this->client->createService(DeleteService::class);
        $predicate = new DeletePredicateRequest();
        $predicate->setStart(Carbon::now()->subYears(30)->toRfc3339String());
        $predicate->setStop(Carbon::now()->toRfc3339String());
        $predicate->setPredicate(sprintf('_measurement="kline" AND symbol="%s"', $symbol));
        $srv->postDelete($predicate, null, $this->org, $this->bucket);
        $this->client->close();
        return true;
    }

    /**
     * 单条数据写入
     * @param string $symbol
     * @param string $interval
     * @param array $kline
     * @return true
     * @throws InvalidArgumentException
     * @throws ApiException
     */
    public function writeData(string $symbol, string $interval, array $kline): true
    {
        // kline 示例
        // $kline = [
        //     [
        //         'o' => 0.1942,
        //         'c' => 0.1953,
        //         'h' => 0.1953,
        //         'l' => 0.1942,
        //         'v' => 1997,
        //         'tl' => 1742846520000,
        //     ],
        // ];

        $writeApi = $this->client->createWriteApi([
            'writeOptions' => [
                'writeType' => WriteType::SYNCHRONOUS // 立即发送，不缓冲
            ]
        ]);

        $points = [];
        $count  = count($kline);
        foreach ($kline as $item) {
            $content  = json_encode([
                "o"  => $item['o'],
                "c"  => $item['c'],
                "h"  => $item['h'],
                "l"  => $item['l'],
                "v"  => $item['v'],
                "co" => $count,
                "tl" => $item['tl'],
            ]);
            $point    = Point::measurement('kline')
                ->addTag("symbol", strtolower($symbol))
                ->addTag("interval", $interval)
                ->addField("content", $content)
                ->time($item['tl'], WritePrecision::MS);
            $points[] = $point;
        }
        try {
            $writeApi->write($points);
        } catch (ApiException $e) {
            Log::error('writeData InfluxDB write failed: '.$e->getMessage(), [
                'symbol'   => $symbol,
                'interval' => $interval,
                'code'     => $e->getCode(),
                'response' => $e->getResponseBody()
            ]);
            throw new LogicException(__('Failed to write data to InfluxDB'));
        } catch (\Exception $e) {
            Log::error('writeData InfluxDB unexpected error: '.$e->getMessage());
            throw new LogicException(__('Internal error when writing data'));
        } finally {
            $writeApi->close();
        }
        return true;
    }

    public function updateData()
    {
        $w     = $this->client->createWriteApi();
        $point = Point::measurement("zfsusdt")
            ->addTag("symbol", "zfsusdt")
            ->addTag("interval", "1m")
            ->addField("content", json_encode([
                "o"  => '0.1950',
                "c"  => '0.1953',
                "h"  => '0.1953',
                "l"  => '0.1942',
                "v"  => '1997',
                "co" => 1,
                "tl" => '1742846520000',
            ]))
            ->time(1742846520000, WritePrecision::MS);
        $w->write($point);
        $w->close();
        return true;
    }

    /**
     *
     * 查询k线历史数据
     *
     * @param string $binanceSymbol
     * @param string $interval
     * @return array
     * @throws InvalidArgumentException
     * @throws FluxQueryError
     * @throws FluxCsvParserException
     * @throws RuntimeException
     */
    public function queryKline(string $binanceSymbol, string $interval, string $start = '-1d')
    {
        $binanceSymbol = strtolower($binanceSymbol);
        $query         = <<<sql
from(bucket: "%s")
  |> range(start: %s)
  |> filter(fn: (r) => r["_measurement"] == "%s")
  |> filter(fn: (r) => r["symbol"] == "%s")
  |> filter(fn: (r) => r["interval"] == "%s")
  |> sort(columns: ["_time"], desc: false)
sql;
        $query         = sprintf(
            $query,
            $this->bucket,
            $start,
            self::DefaultKlineSchema,
            $binanceSymbol,
            $interval,
        );

        $data = $this->queryApi->queryStream($query);
        $resp = [];
        foreach ($data->each() as $record) {
            $v = $record->getValue();
            if (!$v) {
                continue;
            }
            $resp[] = json_decode($v, true);
        }

        if ($binanceSymbol == 'dsvusdc' || $binanceSymbol == 'iswusdc' || $binanceSymbol == 'nsyusdc') {
            $resp = collect($resp)->filter(function ($item) {
                if ($item['tl'] >= '1756372200000' && $item['tl'] <= '1756410600000') {
                    if ($item['v'] <= '100000') {
                        return false;
                    }
                }
                return true;
            })->values()->all();
            return $resp;
        }
        if ($binanceSymbol == 'syvusdc') {
            // 1756372200
            $resp = collect($resp)->filter(function ($item) {
                if ($item['tl'] >= '1756372200000' && $item['tl'] <= '1756410600000') {
                    if ($item['v'] <= '100000') {
                        return false;
                    }
                }
                return true;
            })->values()->all();

            return $resp;
        }

        // 去除重复时间戳(刷数据问题)
        // if ($binanceSymbol == 'ulxusdc') {
        //     // 1756420200000
        //     if (in_array($interval, [IntervalEnums::Interval15Minutes, IntervalEnums::Interval30Minutes,])) {
        //         $lastKline = null;
        //         $resp      = collect($resp)->map(function ($item) use (&$lastKline) {
        //             if ($item['tl'] < '1756402200000' || $item['tl'] >= '1756452600000') {
        //                 return $item;
        //             }

        //             if ($lastKline == null) {
        //                 $lastKline = $item;
        //                 return $item;
        //             }
        //             if ($item['o'] != $lastKline['c']) {
        //                 $item['o'] = $lastKline['c'];
        //                 $item['l'] = min($item['c'], $item['o'], $item['l'], $item['h']);
        //                 $item['h'] = max($item['c'], $item['o'], $item['l'], $item['h']);
        //             }
        //             $lastKline = $item;
        //             return $item;
        //         });

        //     }

        //     if ($interval == IntervalEnums::Interval1Day) {
        //         // 1day 只保留有成交量的
        //         $resp = collect($resp)->map(function ($item) {
        //             if ($item['tl'] == '1756339200000') {
        //                 $item['o'] = '0.3157';
        //                 $item['c'] = '0.323';
        //                 $item['h'] = '0.333';
        //                 $item['l'] = '0.3157';
        //             }
        //             return $item;
        //         })->values()->all();

        //         $resp = collect($resp)->filter(function ($item) {
        //             $errorKline = in_array($item['tl'], [
        //                 '1756392900000',
        //                 '1756400100000',
        //                 '1756364400000',
        //             ]);
        //             if ($errorKline) {
        //                 return false;
        //             }
        //             return true;
        //         })->values()->all();

        //         return $resp;
        //     }

        //     $resp = collect($resp)->filter(function ($item) {
        //         if ($item['tl'] <= 1756385100000) {
        //             return true;
        //         }
        //         if (isset($item['co'])) {
        //             return true;
        //         }
        //         return false;
        //     })->values()->all();

        //     $resp = collect($resp)->map(function($item){
        //         if ($item['tl'] >= '1756445400000' && $item['tl'] <= '1756483920000') {
        //             $item['o'] = bcmul($item['o'],0.88,4);
        //             $item['c'] = bcmul($item['c'],0.88,4);
        //             $item['h'] = bcmul($item['h'],0.88,4);
        //             $item['l'] = bcmul($item['l'],0.88,4);
        //         }
        //         return $item;
        //     });

        // }
        return $resp;
    }

    public function queryMultipleKline(array $symbols, string $interval, string $start = '-1d')
    {
        $binanceSymbols = [];
        collect($symbols)->each(function ($item) use (&$binanceSymbols) {
            $item = strtolower($item);
            array_push($binanceSymbols, 'r["symbol"] == "'.$item.'" ');
            return true;
        });
        $binanceSymbols = implode('or ', $binanceSymbols);

        $query = <<<sql
from(bucket: "%s")
  |> range(start: %s)
  |> filter(fn: (r) => r["_measurement"] == "%s")
  |> filter(fn: (r) => %s)
  |> filter(fn: (r) => r["interval"] == "%s")
  |> sort(columns: ["_time"], desc: false)
sql;
        $query = sprintf(
            $query,
            $this->bucket,
            $start,
            self::DefaultKlineSchema,
            $binanceSymbols,
            $interval,
        );

        $data = $this->queryApi->queryStream($query);
        $resp = [];
        foreach ($data->each() as $record) {
            $curSymbol = $record->values['symbol'] ?? '';
            $v         = $record->getValue();
            if (!$v || !$curSymbol) {
                continue;
            }
            $resp[$curSymbol][] = json_decode($v, true);
        }
        return $resp;
    }

    public function writeMultiData($symbol, $klines, $interval = '1m')
    {
        $symbol = strtolower($symbol);

        foreach ($klines as $kline) {
            // 准备K线内容JSON
            $content = json_encode([
                'o'  => $kline['open'],
                'c'  => $kline['close'],
                'h'  => $kline['high'],
                'l'  => $kline['low'],
                'v'  => $kline['volume'],
                'co' => $kline['count'] ?: 0,
                'tl' => $kline['time'],
            ]);

            // 创建数据点
            $point = Point::measurement($symbol)
                ->addTag('symbol', $symbol)
                ->addTag('interval', $interval)
                ->addField('content', $content)
                ->time(strtotime($kline['time']) * 1000, WritePrecision::MS); // 转换为毫秒时间戳

            // 写入数据点
            $w = $this->client->createWriteApi();
            $w->write($point);
            $w->close();
        }

        return true;
    }

    // 假设1分钟K线数据是一个数组，每项是关联数组：['tl' => unix_timestamp, 'o' => float, 'h' => float, 'l' => float, 'c' => float, 'v' => float]
    // 数据必须按时间戳升序排序
    // 示例使用
    // $min1Data = [ ... ]; // 你的1分钟数据数组
    // $min5 = aggregateKlines($min1Data, '5m');
    function aggregateKlines($symbol, $interval)
    {
        $data = $this->queryKline($symbol, '1m', '-y');
        if (empty($data)) {
            return [];
        }

        $aggregated   = [];
        $currentGroup = null;
        $groupKey     = null;

        foreach ($data as $bar) {
            $timestamp = $bar['tl'];
            $dt        = new DateTime("@$timestamp");
            $dt->setTimezone(new DateTimeZone('UTC')); // 假设时间戳是UTC

            switch ($interval) {
                case '5m':
                    // 5分钟：将分钟向下取整到最近的5分钟倍数
                    $minute      = (int)$dt->format('i');
                    $startMinute = floor($minute / 5) * 5;
                    $dt->setTime((int)$dt->format('H'), $startMinute, 0);
                    $newKey = $dt->getTimestamp();
                    break;
                case '15m':
                    $minute      = (int)$dt->format('i');
                    $startMinute = floor($minute / 15) * 15;
                    $dt->setTime((int)$dt->format('H'), $startMinute, 0);
                    $newKey = $dt->getTimestamp();
                    break;
                case '30m':
                    $minute      = (int)$dt->format('i');
                    $startMinute = floor($minute / 30) * 30;
                    $dt->setTime((int)$dt->format('H'), $startMinute, 0);
                    $newKey = $dt->getTimestamp();
                    break;
                case '1d':
                    // 1天：设置到当天的00:00:00
                    $dt->setTime(0, 0, 0);
                    $newKey = $dt->getTimestamp();
                    break;
                case '1w':
                    // 1周：自然周，从周一到周日。找到本周周一
                    $weekday = (int)$dt->format('N'); // 1=周一,7=周日
                    $dt->modify('-'.($weekday - 1).' days');
                    $dt->setTime(0, 0, 0);
                    $newKey = $dt->getTimestamp();
                    break;
                case '1M':
                    // 1月：自然月，从1号00:00:00
                    $dt->setDate((int)$dt->format('Y'), (int)$dt->format('m'), 1);
                    $dt->setTime(0, 0, 0);
                    $newKey = $dt->getTimestamp();
                    break;
                default:
                    throw new LogicException("Unsupported interval: $interval");
            }

            if ($newKey !== $groupKey) {
                if ($currentGroup !== null) {
                    $aggregated[] = $currentGroup;
                }
                $groupKey     = $newKey;
                $currentGroup = [
                    'tl' => $groupKey * 1e3,
                    'o'  => $bar['o'],
                    'h'  => $bar['h'],
                    'l'  => $bar['l'],
                    'c'  => $bar['c'],
                    'v'  => $bar['v']
                ];
            } else {
                $currentGroup['h'] = max($currentGroup['h'], $bar['h']);
                $currentGroup['l'] = min($currentGroup['l'], $bar['l']);
                $currentGroup['c'] = $bar['c'];
                $currentGroup['v'] += $bar['v'];
            }
        }

        if ($currentGroup !== null) {
            $aggregated[] = $currentGroup;
        }

        $this->writeData('ulxusdc', $interval, $aggregated);

        return $aggregated;
    }


}
