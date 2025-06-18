<?php

namespace App\Console\Commands;

use App\Enums\IEOEnums;
use App\Models\IeoSymbol;
use Carbon\Carbon;
use Illuminate\Console\Command;

class HandleIeo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ieo:run';

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
        $symbosl = IeoSymbol::where('status','<', IEOEnums::StatusCompleted)->get();
        if ($symbosl->isEmpty()) {
            return $this->info('done : no data found');
        }

        $now = Carbon::now();
        $symbosl->each(function($item) use($now){
            $status = '';
            switch($item->status) {
                case IEOEnums::StatusWaiting:
                    if (Carbon::now()->greaterThanOrEqualTo(Carbon::parse($item->order_start_time))) {
                        $status = IEOEnums::StatusPending;
                    }                   
                    break;
                case IEOEnums::StatusPending:
                    if (Carbon::now()->greaterThanOrEqualTo(Carbon::parse($item->order_end_time))) {
                        $status = IEOEnums::StatusProcessing;
                    }
                    break;
                case IEOEnums::StatusProcessing:
                    if (Carbon::now()->greaterThanOrEqualTo(Carbon::parse($item->release_time))) {
                        $status = IEOEnums::StatusCompleted;
                    }
                    break;
            }
            if ($status) {
                $item->status = $status;
                $item->save();
            }
            return true;
        });

        return $this->info('done');
    }
}
