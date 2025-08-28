<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Internal\Market\Actions\GenerateKline;
use Internal\Market\Services\InfluxDB;

class FixKlineData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'influx:fix';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
    $START   = '2025-08-28 16:55:00';
    $END     = '2025-08-28 18:20:00';
    $OPEN0   = 0.3390;
    $CLOSE1  = 0.3381;
    $LOW     = 0.3377;
    $HIGH    = 0.3391;
    $SEED    = 9527;

        $opts = [
        'maxWickPctOfPrice' => 0.003, 
        'maxWickPips'       => 22, 
        'wiggleFracOfRange' => 0.25,
        'useExtremeWicks'   => false,
        'hardWickPctOfPricePerInterval' => [
            '15m' => 0.0030,
            '30m' => 0.0030,
            '1d'     => 0.0035,   // 0.18%
            '1w'     => 0.0036,
            '1mo' => 0.0038,   // 0.22%
        ],
        'hardWickPipsPerInterval' => [
            '15m' => 18,
            '30m' => 18,
            '1d'     => 18,       // 0.0012
            '1w'     => 18,
            '1mo' => 18,       // 0.0016
        ],
    ];

    // (new InfluxDB('market_spot'))->deleteData('ulxusdc'); 

    $eng =(new GenerateKline($START, $END, $HIGH, $LOW, $OPEN0, $CLOSE1, $SEED, $opts));

    // 默认不导出 1m（体量巨大）。如需导出，取消下面两行注释，并启用 1m 输出。
    // $eng->addCsvSink('1m', "$OUTDIR/kline_1m.csv")->enable1mOutput(true);
    // 或仅用回调：$eng->enable1mOutput(true, fn($bar)=>/*写库*/);

    $OUTDIR = './kline_influx_php';
    if (!is_dir($OUTDIR)) mkdir($OUTDIR, 0777, true);

    $measurement = 'kline';
    $symbol      = 'ulxusdc';

    $eng->addCallbackSink('1m', function($bar) use ($symbol) {
        $bar['tl'] = $bar['tl'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData($symbol,'1m',[$bar]);
    })->addCallbackSink('5m', function($bar) use ($symbol) {
        $bar['tl'] = $bar['tl'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData($symbol,'5m',[$bar]);
    })->addCallbackSink('15m', function($bar) use ($symbol) {
        $bar['tl'] = $bar['tl'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData($symbol,'15m',[$bar]);
    })->addCallbackSink('30m', function($bar) use ($symbol) {
        $bar['tl'] = $bar['tl'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData($symbol,'30m',[$bar]);
    })->addCallbackSink('1h', function($bar) use ($symbol) {
        $bar['tl'] = $bar['tl'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData($symbol,'1h',[$bar]);
    })->addCallbackSink('1d', function($bar) use ($symbol) {
        $bar['tl'] = $bar['tl'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData($symbol,'1d',[$bar]);
    })->addCallbackSink('1w', function($bar) use ($symbol) {
        $bar['tl'] = $bar['tl'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData($symbol,'1w',[$bar]);
    })->addCallbackSink('1mo', function($bar) use ($symbol) {
        $bar['tl'] = $bar['tl'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData($symbol,'1mo',[$bar]);
    });

    $t0 = microtime(true);
    $eng->run();
    $eng->close();
    $dt = microtime(true) - $t0;
    $this->info("Kline data fixed successfully in {$dt} seconds.");
    return $this->info('ok');

    $eng->addInfluxCsvSink('1m',     "$OUTDIR/kline_1m.csv",     $measurement, ['symbol'=>$symbol,'interval'=>'1m'])->enable1mOutput(true);
    $eng->addInfluxCsvSink('5m',     "$OUTDIR/kline_5m.csv",     $measurement, ['symbol'=>$symbol,'interval'=>'5m']);
    $eng->addInfluxCsvSink('15m',    "$OUTDIR/kline_15m.csv",    $measurement, ['symbol'=>$symbol,'interval'=>'15m']);
    $eng->addInfluxCsvSink('30m',    "$OUTDIR/kline_30m.csv",    $measurement, ['symbol'=>$symbol,'interval'=>'30m']);
    $eng->addInfluxCsvSink('1h',     "$OUTDIR/kline_1h.csv",     $measurement, ['symbol'=>$symbol,'interval'=>'1h']);
    $eng->addInfluxCsvSink('1d',     "$OUTDIR/kline_1d.csv",     $measurement, ['symbol'=>$symbol,'interval'=>'1d']);
    $eng->addInfluxCsvSink('1w',     "$OUTDIR/kline_1w.csv",     $measurement, ['symbol'=>$symbol,'interval'=>'1w']);
    $eng->addInfluxCsvSink('1mo', "$OUTDIR/kline_1month.csv", $measurement, ['symbol'=>$symbol,'interval'=>'1mo']);

    $t0 = microtime(true);
    $eng->run();
    $eng->close();
    $dt = microtime(true) - $t0;
    $this->info("Kline data fixed successfully in {$dt} seconds.");

    return $this->info('ok');

    // 常用周期导出到 CSV：
    $eng->addCallbackSink('1m', function($bar){
        $bar['tl'] = $bar['t'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData('ulxusdc','1m',[$bar]);
    })->enable1mOutput(true)
    ->addCallbackSink('5m',function($bar){
        $bar['tl'] = $bar['t'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData('ulxusdc','5m',[$bar]);
    })->addCallbackSink('15m', function($bar){
        $bar['tl'] = $bar['t'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData('ulxusdc','15m',[$bar]);
    })->addCallbackSink('30m', function($bar){
        $bar['tl'] = $bar['t'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData('ulxusdc','30m',[$bar]);
    })->addCallbackSink('1h', function($bar) {
        $bar['tl'] = $bar['t'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData('ulxusdc','1h',[$bar]);
    })->addCallbackSink('1d', function($bar){
        $bar['tl'] = $bar['t'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData('ulxusdc','1d',[$bar]);
    })->addCallbackSink('1w', function($bar){
        $bar['tl'] = $bar['t'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData('ulxusdc','1w',[$bar]);
    })->addCallbackSink('1M', function($bar){
        $bar['tl'] = $bar['t'].'000';
        $srv = new InfluxDB('market_spot');
        $srv->writeData('ulxusdc','1M',[$bar]);
    });

    $t0 = microtime(true);
    $eng->run();
    $eng->close();
    $dt = microtime(true) - $t0;
    $this->info("Kline data fixed successfully in {$dt} seconds.");
}
}
