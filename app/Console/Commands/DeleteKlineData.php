<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Internal\Market\Services\InfluxDB;

class DeleteKlineData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-kline-data';

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
        $srv->deleteData('synusdc');
        $srv->deleteData('nsyusdc');
        $srv->deleteData('iswusdc');
        $srv->deleteData('gpuusdc');
        $srv->deleteData('dsvusdc');
    }
}
