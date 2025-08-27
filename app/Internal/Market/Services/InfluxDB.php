<?php

namespace Internal\Market\Services;

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
        $this->addr = config('influxdb.addr', '');
        $this->token = config('influxdb.token', '');
        $this->bucket = $bucket;
        $this->org = config('influxdb.org', '');

        if (!$this->addr || !$this->token || !$this->bucket || !$this->org) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }
        $this->client = new Client([
            'url' => $this->addr,
            'token' => $this->token,
            'bucket' => $this->bucket,
            'org' => $this->org,
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
        $srv = $this->client->createService(DeleteService::class);
        $predicate = new DeletePredicateRequest();
        $predicate->setStart(Carbon::now()->subYears(30)->toRfc3339String());
        $predicate->setStop(Carbon::now()->toRfc3339String());
        $predicate->setPredicate(sprintf('_measurement="kline" AND symbol="%s"', $symbol));
        $srv->postDelete($predicate, null, $this->org, $this->bucket);
        $this->client->close();
        return true;
    }

    /**
     * 写入数据
     * @param string $symbol
     * @param string $interval
     * @param array $kline
     * @return true
     * @throws InvalidArgumentException
     * @throws ApiException
     */
    public function writeData(string $symbol, string $interval,array $kline): true
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

        $w = $this->client->createWriteApi(
            ["writeType" => WriteType::BATCHING, 'batchSize' => 1000]
        );

        foreach ($kline as $item) {
            $content = json_encode([
                "o"  => $item['o'],
                "c"  => $item['c'],
                "h"  => $item['h'],
                "l"  => $item['l'],
                "v"  => $item['v'],
                "co" => 0,
                "tl" => $item['tl'],
            ]);
            $point = Point::measurement($symbol)
                ->addTag("symbol", $symbol)
                ->addTag("interval", $interval)
                ->addField("content", $content)
//                ->addField("o", $item['o'])
//                ->addField("c", $item['c'])
//                ->addField("h", $item['h'])
//                ->addField("l", $item['l'])
//                ->addField("v", $item['v'])
//                ->addField("tl", $item['tl']) // 毫秒
                ->time($item['tl'], WritePrecision::MS);
            $w->write($point);
        }
        $w->close();
        return true;
    }

    public function updateData() {
        $w = $this->client->createWriteApi();
        $point = Point::measurement("zfsusdt")
            ->addTag("symbol", "zfsusdt")
            ->addTag("interval", "1m")
            ->addField("o", '0.1942')
            ->addField("c", '0.1953')
            ->addField("h", '0.1953')
            ->addField("l", '0.1942')
            ->addField("v", '1997')
            ->addField("tl", '1742846520000')
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
        $query = <<<sql
from(bucket: "%s")
  |> range(start: %s)
  |> filter(fn: (r) => r["_measurement"] == "%s")
  |> filter(fn: (r) => r["symbol"] == "%s")
  |> filter(fn: (r) => r["interval"] == "%s")
  |> sort(columns: ["_time"], desc: false)
sql;
        $query = sprintf(
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
        if ($binanceSymbol == 'zfsusdt') {
        $resp = collect($resp)
            ->groupBy('tl')
            ->flatMap(function ($group) {
                // if ($tl <= '1742842800000') {
                //     return $group->filter(function ($item) {
                //         return !empty($item['co']);
                //     });
                // }
                // return $group->filter(function ($item) {
                //     return empty($item['co']);
                // });

                $hasNonEmptyData = $group->contains(function ($item) {
                    return !empty($item['co']);
                });

                if ($hasNonEmptyData) {
                    return $group->filter(function ($item) {
                        return !empty($item['co']);
                    });
                }
                return $group;
            })
            ->values()
            ->all();
        }
        return $resp;
    }

    public function queryMultipleKline(array $symbols, string $interval, string $start = '-1d')
    {
        $binanceSymbols = [];
        collect($symbols)->each(function ($item) use (&$binanceSymbols) {
            $item = strtolower($item);
            array_push($binanceSymbols, 'r["symbol"] == "' . $item . '" ');
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
            $v = $record->getValue();
            if (!$v || !$curSymbol) {
                continue;
            }
            $resp[$curSymbol][] = json_decode($v, true);
        }
        return $resp;
    }
}
