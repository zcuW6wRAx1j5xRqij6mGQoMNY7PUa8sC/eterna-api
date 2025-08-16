<?php


namespace App\Console\Commands;

use App\Enums\FundsEnums;
use App\Events\UserCreated;
use App\Models\User;
use App\Models\UserInbox;
use App\Models\UserLevel;
use App\Models\UserOrderFutures;
use App\Notifications\SendRegisterCaptcha;
use App\Notifications\SendRegisterPhoneCaptcha;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Internal\Common\Actions\SendCloud;
use Internal\Market\Services\BinanceService;
use Internal\Order\Actions\TradeCalculator;
use Internal\Pay\Services\UdunService;
use Internal\Tools\Services\CentrifugalService;

class Test extends Command
{
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
    public function handle()
    {
        for ($i=1;$i<=100;$i++) {
              $user = new User();
              $user->email = 'test_'.$i.'@gmail.com';
              $user->password = Hash::make('Aa.123123');
              
            $user->level_id         = UserLevel::getFirstLevel();
            $user->name             = 'user:'.Str::random(8);
            $user->register_ip      = '127.0.0.1';
            $user->register_device  = 'null';
            $user->parent_id        = 'null';
            $user->salesman         = 0;

            $user->save();
            // 创建钱包
            UserCreated::dispatch($user);
        }
        return $this->info('ok');


    //     $account = '855887203829';
    //     $captcha = '123456';
    //     // $phone = trim($account,'00');

    //     Notification::route('phone',$account)->notify(new SendRegisterPhoneCaptcha($captcha));
    //     dd('ok');

    // //     id         bigint unsigned auto_increment
    // //     primary key,
    // // uid        bigint            not null comment '用户UID',
    // // subject    text              not null comment '主题',
    // // content    longtext          not null comment '消息内容',
    // // is_read    tinyint default 0 not null comment '是否已读 : 0 否 1是',
    // // created_at timestamp         null,
    // // updated_at timestamp         null

    //     User::get()->each(function($item){

    //         $box = new UserInbox();
    //         $box->uid = $item->id;
    //         $box->subject = 'Sehr geehrte LSDX-Nutzer';
    //         $box->content = '<p><strong>Sehr geehrte LSDX-Nutzer,</strong></p><p><br></p><p>Hallo! Vielen Dank für Ihre Unterstützung von LSDX. Um die Leistung und Benutzererfahrung der Plattform zu verbessern, führen wir ein umfassendes Upgrade des LSDX-Handelssystems durch, das hauptsächlich die AI 5.0 Smart Contract-Technologie, die Optimierung des strategischen Zuteilungssystems und die Verbesserung der Benutzeroberfläche (UI) umfasst. Unser Ziel ist es, eine sicherere, stabilere und benutzerfreundlichere Handelsplattform anzubieten.</p><p><br></p><p>Während dieses Prozesses kann es zu kurzen Verzögerungen oder Ausfällen des Systems kommen, wir bitten um Ihr Verständnis. Sollte dies der Fall sein, versuchen Sie bitte, sich abzumelden und erneut anzumelden oder den Browser-Cache zu löschen. Falls das Problem weiterhin besteht, können Sie sich jederzeit an den LSDX-Kundendienst wenden, und wir werden Ihnen gerne helfen.</p><p><br></p><p>Vielen Dank für Ihr Verständnis und Ihre Geduld. LSDX wird kontinuierlich optimieren, um Ihnen ein besseres Handelserlebnis zu bieten.</p><p><br></p><p>Mit freundlichen Grüßen,</p><p><br></p><p>LSDX Offizielle Bekanntmachung</p>';
    //         $box->save();
    //         return true; 
    //     });
    }
}
