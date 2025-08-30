<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class FilterKline extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:filter-kline';
    
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
        $zKey  = 'dddusdc:1m';
        $redis = Redis::connection();
        $redis->select(3);
        $length = $redis->zCount($zKey, '-inf', '+inf');
        for ($i = 0; $i < $length; $i++) {
            $item = $redis->zRange($zKey, $i, $i, true);
            $key  = array_keys($item);
            $key  = $key[0];
            $data = json_decode($key, true);
            if ($data['o'] != $data['c']) {
                $max = max($data['o'], $data['c']);
                $min = min($data['o'], $data['c']);
            } else {
                $max = $min = $data['o'];
            }
            $rate      = 1 + (float)bcdiv(rand(50, 2000), 10000000, 8);
            $data['h'] = bcmul($max, $rate, 8);
            $rate      = 1 - (float)bcdiv(rand(50, 2000), 10000000, 8);
            $data['l'] = bcmul($min, $rate, 8);
//            $offsetOpen = bcsub($data['h'], $data['o'], 8);
//            $rateOpen   = bcdiv($offsetOpen, $data['o'], 8);
//            if ($rateOpen > 0.009) {
//                dd($data);
//            }
//            $offsetClose = bcsub($data['c'], $data['l'], 8);
//            $rateClose   = bcdiv($offsetClose, $data['c'], 8);
//            if ($rateClose > 0.009) {
//                dd($data);
//            }
            $data['v']  = rand(1000, 9999);
            $data['tl'] = (int)$item[$key];
            $redis->zAdd('dddusdc:1o', $data['tl'], json_encode($data));
            $this->info($i);
        }
    }
}
