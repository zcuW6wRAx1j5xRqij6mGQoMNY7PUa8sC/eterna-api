<?php

namespace Internal\Common\Services;

use App\Enums\ConfigEnums;
use App\Models\PlatformConfig;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ConfigService {

    private $cacheData = [];

    const CfgCacheKey = 'cache.data.config';

    private static $ins = null;

    public static function getIns() {
        if (self::$ins == null) {
            self::$ins = new self();
        }
        return self::$ins;
    }

    private function __construct()
    {

    }

    // 刷新缓存
    public function refresh() {
        $this->cacheData = [];
        Cache::forget(self::CfgCacheKey);
        $this->cache();
    }

    /**
     * 获取配置项
     * @param string $key
     * @return mixed
     */
    public function fetch(string $key, mixed $default = null) {
        $key = ConfigEnums::getFullname($key);
        if (!$key) {
            return $default;
        }
        return Arr::get($this->cache(),$key,$default);
    }



    /**
     * 缓存处理
     * @return mixed
     */
    public function cache() {
        if ($this->cacheData) {
            return $this->cacheData;
        }

        $this->cacheData = Cache::rememberForever(self::CfgCacheKey,function(){
            $data = [];
            PlatformConfig::all()->each(function($i) use (&$data){
                $data[$i->category][$i->key] = $i->value;
                return true;
            });
            return $data;
        });
        return $this->cacheData;
    }

    /**
     * 删除缓存
     * @return bool
     */
    public function forget() {
        return Cache::forget(self::CfgCacheKey);
    }
}
