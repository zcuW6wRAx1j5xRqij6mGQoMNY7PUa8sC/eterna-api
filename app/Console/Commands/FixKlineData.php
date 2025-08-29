<?php

namespace App\Console\Commands;

use Carbon\Carbon;
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
        $generator = new GenerateKline(
            'dddusdc',
            '2024-09-01 08:00:00',  // 开始时间
            '2025-08-29 20:30:00',  // 结束时间
            0.3670,
            0.1120,
            0.1321,
            0.3613,
        );
        $generator->exportToCSV('1m', './kline_1m.csv');
        dd('ok');
    }

}
