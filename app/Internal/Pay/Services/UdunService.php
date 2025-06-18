<?php

namespace Internal\Pay\Services;

use App\Exceptions\InternalException;
use App\Exceptions\LogicException;
use App\Models\PlatformWallet;
use App\Models\UserWithdraw;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UdunService {

    // 生成钱包
    const GenerateWalletUri = '/mch/address/create';
    // 提币
    const WithdrawUri = '/mch/withdraw';
    // 币信息
    const CoinsInfoUri = '/mch/support-coins';

    // 毁掉地址
    const SelfCallbackPath = '/api/thrid_party/wallet/callback';

    private $apiKey = '';
    private $merchantId = '';
    private $apiNode = '';


    public function __construct()
    {
        $this->apiKey = config('udun.api_key','');
        $this->apiNode = config('udun.api_node','');
        $this->merchantId = config('udun.merchant_id','');

        if (!$this->apiKey || !$this->apiNode || !$this->merchantId) {
            throw new InternalException('无法初始化U盾 , 配置信息不正确');
        }

    }


    /**
     * 生成回调地址
     * @return string
     * @throws BindingResolutionException
     */
    private function generateCallbackPath(int $uid =0) {
        $path = config('app.url').self::SelfCallbackPath;
        if ($uid) {
            $path = $path.'/'.$uid;
        }
        return $path;
    }

    /**
     * 提现
     * @param UserWithdraw $userWithdraw
     * @param PlatformWallet $platformWallet
     * @param int $uid
     * @return never
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function withdraw(UserWithdraw $userWithdraw,int $uid = 0) {
        $platform = PlatformWallet::find($userWithdraw->wallet_id);
        if (!$platform) {
            Log::error('提现失败 , 没有找到对应的u盾配置',[
                'withdraw_record_id'=>$userWithdraw->id,
            ]);
            throw new LogicException('server error');
        }

        $data = [
            'address'=>$userWithdraw->receive_wallet_address,
            'amount'=>$userWithdraw->real_amount,
            'merchantId'=>$this->merchantId,
            'mainCoinType'=>$platform->udun_main_coin_type,
            'coinType'=>$platform->udun_coin_type,
            'callUrl'=>$this->generateCallbackPath(),
            'businessId'=>$userWithdraw->order_no,
        ];
        $resp = $this->call(self::WithdrawUri, $data);
        $code = $resp['code'] ?? 0;
        if ($code != '200') {
            Log::error('failed to request withdraw',[
                'data'=>$data,
                'resp'=>$resp,
            ]);
            throw new LogicException('server error');
        }
        Log::info('发起u盾提现',['data'=>$data,'resp'=>$resp]);
        return true;
    }

    /**
     * 生成钱包地址
     * @param string $coinType
     * @return mixed
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function generateWallet(string $coinType, int $uid = 0) {
        $data = [
            'merchantId'=>$this->merchantId,
            'mainCoinType'=>$coinType,
            'callUrl'=>$this->generateCallbackPath($uid),
        ];
        Log::info("请求u盾生成钱包地址",[
            'data'=>$data,
        ]);
        $resp = $this->call(self::GenerateWalletUri, $data);
        $address = $resp['data']['address'] ?? '';
        if (!$address) {
            Log::error('failed to request user wallet address',[
                'coin_type'=>$coinType,
                'resp'=>$resp,
            ]);
            throw new LogicException('server error');
        }
        return $address;
    }

    public function getAllCoins() {
        $data = [
            'merchantId'=>$this->merchantId,
            'showBalance'=>true,
        ];
        return $this->call(self::CoinsInfoUri, $data);
    }

    private function signature(string $body, string $time, int $nonce) {
        return md5($body.$this->apiKey.$nonce.$time);
    }

    public function checkSignature(string $body , string $t, string $nonce, string $sign) {
        return strcmp($sign,md5($body.$this->apiKey.$nonce. $t)) === 0;
    }

    private function generateNonce() {
    	return rand(100000, 999999);
    }

    private function call(string $uri, array $body = []) {
        $url = $this->apiNode. $uri;

        if($uri=='/mch/support-coins'){
            $body = json_encode($body);
        }else{
            $body = '['.json_encode($body).']';
        }
        $t = time();
        $n = $this->generateNonce();
        $sign = $this->signature($body,$t,$n);
        $params = array(
        	'timestamp' => $t,
            'nonce' => $n,
            'sign' => $sign,
            'body' => $body
        );

        $resp = Http::timeout(10)->asJson()->post($url, $params);
        if (!$resp->ok()) {
            Log::error('访问u盾接口失败',[
                'url'=>$url,
                'body'=>$body,
            ]);
            return false;
        }
        return $resp->json();
    }

}
