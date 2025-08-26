<?php

namespace Internal\Tools\Services;

use App\Exceptions\LogicException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessageSend {

    const ReqUrl = 'https://sms.360.my/gw/bulk360/v3_0/send.php';

    private $apiUser;

    private $apiKey;

    public function __construct()
    {
        $cfg = config('msg',[]);
        $this->apiUser = $cfg['api_user'] ?? '';
        $this->apiKey = $cfg['api_key'] ?? '';
        if (!$this->apiKey ||!$this->apiUser) {
            Log::error('初始化手机验证码失败 ,配置文件不正确');
            throw new LogicException(__('Whoops! Something went wrong'));
        }
    }

    public function send(string $phone, string $text) {
        $msg = rawurlencode(sprintf($this->textPrefix(), $text));
        $payload = sprintf("user=%s&pass=%s&to=%s&text=%s",$this->apiUser, $this->apiKey, $phone, $msg);

        $reqUrl = sprintf(self::ReqUrl.'?%s',$payload);
        $response = Http::get($reqUrl);
        if (!$response->ok()) {
            Log::error('发送手机验证码失败 , http code 不正确',['payload'=>['phone'=>$phone,'text'=>$text],'response'=>$response->json(),'http_code'=>$response->status()]);
            return false;
        }
        $resp = $response->json();
        Log::info('发送手机验证码记录',['resp'=>$resp,'phone'=>$phone, 'text'=>$text]);
        return true;
    }

    private function textPrefix() {
        return "[CoinVitaX] Ihr Bestätigungscode lautet: %s";
    }
    
}