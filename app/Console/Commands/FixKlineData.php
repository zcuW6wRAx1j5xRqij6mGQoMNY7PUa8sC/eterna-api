<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
        $srv = new InfluxDB('market_spot');
        $srv->updateData();
    }
}
