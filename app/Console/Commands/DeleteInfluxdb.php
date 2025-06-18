<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Internal\Market\Services\InfluxDB;

class DeleteInfluxdb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'influx:delete';

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
        $srv = new InfluxDB('market_futures');
        $srv->deleteData();

        $srv2 = new InfluxDB('market_spot');
        $srv2->deleteData();
    }
}
