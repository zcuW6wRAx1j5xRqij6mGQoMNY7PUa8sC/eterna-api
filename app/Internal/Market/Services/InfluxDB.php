<?php

namespace Internal\Market\Services;

use App\Enums\IntervalEnums;
use App\Exceptions\LogicException;
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
            $content = json_encode([
                "o"  => $item['o'],
                "c"  => $item['c'],
                "h"  => $item['h'],
                "l"  => $item['l'],
                "v"  => $item['v'],
                "co" => $count,
                "tl" => $item['tl'],
            ]);
            $point   = Point::measurement('kline')
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
        // 去除重复时间戳(刷数据问题)
        if ($binanceSymbol == 'ulxusdc') {
            if ($interval == IntervalEnums::Interval1Day) {
                // 1day 只保留有成交量的
                $resp = collect($resp)->filter(function($item){
                    $errorKline = in_array($item['tl'],[
                        '1756392900000',
                        '1756400100000'
                        '1756364400000',
                    ]);
                    if ($errorKline) {
                        return false;
                    }
                    return true;
                })->values()->all();
                
                return $resp;
            }

            $resp = collect($resp)->filter(function($item){
                if ($item['tl'] <= 1756385100000) {
                    return true;
                }
                if (isset($item['co'])) {
                    return true;
                }
                return false;
            })->values()->all();
        }
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
}
