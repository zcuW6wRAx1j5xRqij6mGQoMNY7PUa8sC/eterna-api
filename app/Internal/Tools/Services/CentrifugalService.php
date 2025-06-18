<?php

namespace Internal\Tools\Services;

use App\Exceptions\LogicException;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Log;
use phpcent\Client;

class CentrifugalService{

    const JWTAud = 'centrifugo';
    const JWTISS = 'anexocc';
    private $hmacKey = '';
    private $apiKey = '';
    private $apiPath = '';

    private $client;

    private static $ins = null;

    private function __construct()
    {
        $this->hmacKey = config('centrifugo.hmac_key','');
        $this->apiKey = config('centrifugo.api_key','');
        $this->apiPath = config('centrifugo.api_path','');

        if (!$this->apiKey) {
            Log::error("failed to init centrifugal : no api key");
            throw new LogicException(__('Whoops! Something went wrong'));
        }
        if (!$this->apiPath) {
            Log::error("failed to init centrifugal : no api path");
            throw new LogicException(__('Whoops! Something went wrong'));
        }
        if (!$this->hmacKey) {
            Log::error("failed to init centrifugal : no hamc key");
            throw new LogicException(__('Whoops! Something went wrong'));
        }

        $client = new Client($this->apiPath);
        $client->setApiKey($this->apiKey);
        $this->client = $client;
    }

    public function sendMessage(string $channel, array $data) {
        $this->client->publish($channel , $data);
        return true;
    }

    public static function getInstance() {
        if (self::$ins === null) {
            self::$ins = new self();
        }
        return self::$ins;
    }

    /**
     * 生成JWT
     * @param User $user
     * @return string
     * @throws DomainException
     */
    public function generateJWT(User $user, string $channel='') {
        $payload = [
            'aud' => self::JWTAud,
            'iss' => self::JWTISS,
            'exp' => Carbon::now()->addSeconds(User::DefaultTokenTTL)->getTimestamp(),
            'sub'=>(string)$user->id,
        ];
        if ($channel) {
            $payload['channel'] = $channel;
        }
        return JWT::encode($payload, $this->hmacKey,'HS256');
    }

    public function generateAdminJwt(int $uid , string $channel='') {
        $payload = [
            'aud' => self::JWTAud,
            'iss' => self::JWTISS,
            'exp' => Carbon::now()->addSeconds(User::DefaultTokenTTL)->getTimestamp(),
            'sub'=>(string)$uid,
        ];
        if ($channel) {
            $payload['channel'] = $channel;
        }
        return JWT::encode($payload, $this->hmacKey,'HS256');
    }

}

