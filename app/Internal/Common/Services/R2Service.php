<?php

namespace Internal\Common\Services;

use App\Exceptions\LogicException;
use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use LogicException as GlobalLogicException;

class R2Service {

    const EndPointUrl = 'https://%s.r2.cloudflarestorage.com';

    // 一次性链接过期时间 (分钟)
    const PresignedTimeoutMinutes = 5;

    public $bucket = '';
    public $accountId = '';
    public $accountKeyId = '';
    public $accountKeySecret = '';
    public $viewPath = '';

    private $credentials = null;
    private $s3Client = null;


    public function __construct()
    {
        $r2Cfg = config('cloudflare.r2',[]);
        $this->bucket = $r2Cfg['bucket'] ?? '';
        $this->accountId = $r2Cfg['account_id'] ?? '';
        $this->accountKeySecret = $r2Cfg['account_key_secret'] ?? '';
        $this->accountKeyId = $r2Cfg['account_key_id'] ?? '';
        $this->viewPath = $r2Cfg['r2_url'] ?? '';

        if (!$this->accountId || !$this->accountKeySecret || !$this->accountKeyId) {
            Log::error('初始化cloudfalre R2 service失败: 配置不存在或不完整',[
                'config'=>$r2Cfg,
            ]);
            throw new LogicException(__('Whoops! Something went wrong'));
        }

        $this->credentials = new Credentials($this->accountKeyId, $this->accountKeySecret);

        $options = [
            'region' => 'auto',
            'endpoint' => sprintf(self::EndPointUrl, $this->accountId),
            'version' => 'latest',
            'credentials' => $this->credentials,
];
        $this->s3Client = new S3Client($options);
    }

    // 公共访问地址
    public function publicPath() {
        return $this->viewPath;
    }

    /**
     * 获取一次性上传地址
     * @param string $filename
     * @return UriInterface
     * @throws RuntimeException
     * @throws GlobalLogicException
     */
    public function getPutPresignedURLs(string $filename) {
        $cmd = $this->s3Client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $filename,
        ]);
        $req = $this->s3Client->createPresignedRequest($cmd,Carbon::now()->addMinutes(self::PresignedTimeoutMinutes)->unix());
        return $req->getUri();
    }
}
