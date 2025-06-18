<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Internal\Common\Services\ConfigService;

class SyncPlatformConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:config-sync';

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
        ConfigService::getIns()->refresh();
        return $this->info('done');
    }
}
