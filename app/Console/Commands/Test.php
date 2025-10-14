<?php


namespace App\Console\Commands;

use App\Models\Symbol;
use App\Enums\FundsEnums;
use App\Enums\MarketEnums;
use App\Events\UserCreated;
use App\Models\AdminUser;
use App\Models\User;
use App\Models\UserInbox;
use App\Models\UserLevel;
use App\Models\UserOrderFutures;
use App\Models\UserWalletFutures;
use App\Internal\Tools\Services\BotTask;
use App\Notifications\SendRegisterCaptcha;
use App\Notifications\SendRegisterPhoneCaptcha;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Internal\Common\Actions\SendCloud;
use Internal\Market\Actions\GenerateKline;
use Internal\Market\Actions\Kline;
use Internal\Market\Services\BinanceService;
use Internal\Market\Services\InfluxDB;
use Internal\Order\Actions\TradeCalculator;
use Internal\Pay\Services\UdunService;
use Internal\Tools\Services\CentrifugalService;
use Internal\User\Actions\InitUserWallet;

class Test extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '代码测试';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {

        $symbols = \App\Models\Symbol::query()
            ->where('self_data', 1)
            ->where('status', \App\Enums\CommonEnums::Yes)->get();
        if ($symbols->isEmpty()) {
            echo '没有找到合约交易对' . PHP_EOL;
            return ;
        }

        foreach ($symbols as $item) {
            $kline = (new InfluxDB(MarketEnums::SpotInfluxdbBucket))->queryKline($item->binance_symbol,'1M', '-66d');
            if(!$kline){continue;}
            $kline = [(array)$kline[0]];
            (new InfluxDB('market_spot'))->writeData($item->binance_symbol, '1mo', $kline);
        }
        return ;




        $open       = 0.10064;
        $targetHigh = 0.60386;
        $targetLow  = 0.08395;
        $close      = 0.50072;
        $startTime  = '2024-09-01 00:00:00';
        $endTime    = '2025-08-31 23:59:59';
        $sigma      = 0.00001;
        $unit       = '1m';
        $isDel      = 0;
        $symbol     = 'dddusdc';
        $service    = new BotTask();
        $service->generateHistoryData(
            $symbol,
            $open,
            $targetHigh,
            $targetLow,
            $close,
            $startTime,
            $endTime,
            $sigma,
            8,
            $unit,
            $isDel
        );
    }
}
