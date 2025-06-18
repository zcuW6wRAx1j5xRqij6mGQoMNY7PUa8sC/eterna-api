<?php

namespace Internal\Common\Actions;

use App\Enums\CommonEnums;
use App\Exceptions\LogicException;
use App\Models\PlatformNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendCloud {
    const ReqUrl = 'https://api2.sendcloud.net/api/mail/sendtemplate';

    private $apiUser;
    private $apiKey;
    private $from;


    public function __construct()
    {
        $cfg = config('sendcloud',[]);
        $this->apiKey = $cfg['api_key'] ?? '';
        $this->apiUser = $cfg['api_user'] ?? '';
        $this->from = $cfg['from'] ?? '';

        if (!$this->apiKey || !$this->apiUser || !$this->from) {
            Log::error('初始化sendcloud失败, 配置文件不正确');
            throw new LogicException(__('Whoops! Something went wrong'));
        }
    }

    public function send(array $to, string $text) {
        $text = explode('#-#',$text);
        // todo 这里处理容易出问题

        $params = [];
        foreach ($text as $v) {
            $v = explode(':',$v);
            $params[$v[0]] = [$v[1]];
        }
        $payload = [
            'apiUser'=>$this->apiUser,
            'apiKey'=>$this->apiKey,
            'from'=>$this->from,
            'templateInvokeName'=>'anexocc_code',
            'xsmtpapi'=> json_encode([
                "to"=>$to,
                'sub'=> $params,
            ]),
        ];

        $response = Http::asForm()->post(self::ReqUrl, $payload);
        if (!$response->ok()) {
            Log::error('发送邮件失败 : http code 不正确',['payload'=>$payload,'resp'=>$response->json()]);
            throw new LogicException(__('Whoops! Something went wrong'));
        }

        Log::info('发送邮箱验证码记录',['resp'=>$response->json(),'email'=>$to, 'text'=>$text]);


        $respBody = $response->json();
        $result = $respBody['result'] ?? '';
        $statusCode = $respBody['statusCode'] ?? 0;
        if ($statusCode != '200') {
            Log::error('发送邮件失败 : 业务code 不正确',['payload'=>$payload,'resp'=>$response->json()]);
            throw new LogicException(__('Whoops! Something went wrong'));
       }
       return true;
    }

}
