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
    $START   = '2024-01-01 08:00:00';
    $END     = '2025-08-28 12:00:00';
    $OPEN0   = 0.1000;
    $CLOSE1  = 0.5000;
    $LOW     = 0.02000;
    $HIGH    = 0.5000;
    $SEED    = 9527;

    (new InfluxDB('market_spot'))->deleteData('ulxusdc'); 

    $eng =(new GenerateKline($START, $END, $HIGH, $LOW, $OPEN0, $CLOSE1, $SEED));

    // 默认不导出 1m（体量巨大）。如需导出，取消下面两行注释，并启用 1m 输出。
    // $eng->addCsvSink('1m', "$OUTDIR/kline_1m.csv")->enable1mOutput(true);
    // 或仅用回调：$eng->enable1mOutput(true, fn($bar)=>/*写库*/);

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
