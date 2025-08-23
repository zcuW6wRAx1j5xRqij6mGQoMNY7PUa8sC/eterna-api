<?php

/**
 * 友好数字展示
 * @param mixed $num
 * @return string
 */

use App\Enums\CommonEnums;
use App\Enums\FundsEnums;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;
use Symfony\Component\Uid\UuidV4;

if (!function_exists('friendly_number')) {
    function friendly_number($num)
    {
        $num = str_replace(',', '', $num);
        $result = rtrim(rtrim(bcmul($num, 1, 12), 0), '.');

        // 不足两位要补齐
        $decimalDigits = strlen(substr(strrchr($result, "."), 1));
        if ($decimalDigits >= 2) {
            return $result;
        }
        return sprintf("%.2f", $result);
    }
}

if (!function_exists('listResp')) {
    /**
     * @param mixed $num 
     * @return string 
     */
    function parseNumber($num) {
        $lastDot   = strrpos($num, '.');
        $lastComma = strrpos($num, ',');

        if ($lastDot !== false && $lastComma !== false) {
            dump($num);
            // 同时存在 . 和 ,  则确定是 欧洲格式
            // 先去掉千分位
            $num = str_replace('.', '', $num);
            $num = str_replace(',', '.', $num);
        } else {
            $num = str_replace(',', '.', $num);
        }

        if (!is_numeric($num)) {
            return 0;
        }
        return number_format(abs($num), FundsEnums::DecimalPlaces,'.','');
    }
}


if (!function_exists('listResp')) {
    /**
     * 列表返回格式
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     * @param callable|null $callback
     * @param bool $camleConvert
     * @return array
     */
    function listResp(Illuminate\Contracts\Pagination\LengthAwarePaginator|null $paginator, ?callable $callback = null, bool $camleConvert = false)
    {
        if ($paginator === null) {
            $baseInfo = [
                'pages' => 0,
                'items' => [],
                'total' => 0,
            ];
            return $baseInfo;
        }
        $baseInfo = [
            'pages' => $paginator->lastPage(),
            'items' => $paginator->items(),
            'total' => $paginator->total(),
        ];
        if ($camleConvert) {
            $baseInfo['items'] = collect($paginator->items())->map(function ($v) {
                return $v->toArray();
            });
        }
        if (!$callback) {
            return $baseInfo;
        }
        return $callback($baseInfo, $paginator);
    }
}

if (!function_exists('getDeviceInfo')) {
    /**
     * 获取请求必要信息
     * @param Request $request
     * @return (string|null|array)[]
     * @throws ConflictingHeadersException
     */
    function getDeviceInfo(Request $request) {
        return [
            'ip'=>$request->ip(),
            'device_id'=>$request->header('X-Request-DeviceID',''),
            'device_ua'=>$request->header('X-Request-DeviceUA','')
        ];
    }
}



/**
 * 生成文件名称
 * @param string $path
 * @return string
 */
if (!function_exists('generateFilename')) {
    function generateFilename(string $path) {
        return strtoupper(str_replace('-', '', UuidV4::v4()->toRfc4122())).$path;
    }
}

/**
 * 生成订单编号
 * @param string $drawType
 * @return string
 */
if (!function_exists('generateOrderCode')) {
    function generateOrderCode(string $prefix = '') {
        return $prefix.Carbon::now()->format('Ymd').'-'.strtoupper(Str::random('8'));
    }
}

/**
 * 生成邀请码
 * @return string
 */
if (!function_exists('generateInviteCode')) {
    function generateInviteCode() {
        return strtoupper(Str::random(10));
    }
}

if ( !function_exists('generateUuid')) {
    function generateUuid() {
        return UuidV4::v4()->toRfc4122();
    }
}




/**
 * 脱敏手机号
 * @param string $phone
 * @return string
 */
if (!function_exists('maskPhoneNumber')) {
    function maskPhoneNumber(string $phone) {
        return '*******'.substr($phone, -4);
    }

}

if (!function_exists('maskEmailAddr')) {
    /**
     * 脱敏邮箱
     * @param string $email
     * @return string
     */
    function maskEmailAddr(string $email) {
        $parts = explode('@',$email);
        if (count($parts) != 2) {
            return $email;
        }
        $addr = mb_strlen($parts[0]) <= 4 ? substr($email,0,1).'****' : Str::mask($parts[0],'*',-4);
        return $addr .'@'. $parts[1];
    }
}

if(!function_exists('buildNestedArray')){
    function buildNestedArray($items, $parentId = null): array {
        $branch = [];
        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $children = buildNestedArray($items, $item['id']);
                if ($children) {
                    $item['children'] = $children;
                }
                $branch[] = $item;
            }
        }

        return $branch;
    }
}

if (!function_exists('formatPaginate')) {
    /**
     * 格式化分页数据
     * @param Illuminate\Pagination\LengthAwarePaginator $paginator
     * @return array
     */
    function formatPaginate(Illuminate\Pagination\LengthAwarePaginator $paginator): array
    {
        return [
            'total'         => $paginator->total(),
            'currentPage'   => $paginator->currentPage(),
            'lastPage'      => $paginator->lastPage(),
            'hasMorePages'  => $paginator->hasMorePages(),
            'items'         => $paginator->items(),
        ];
    }
}

if (!function_exists('RedisMarket')) {
    function RedisMarket() {
        return Redis::connection('market');
    }
}

if (!function_exists('RedisPosition')) {
    function RedisPosition() {
        return Redis::connection('position');
    }
}


if (!function_exists('floatTransferString')) {
    /**
     * 浮点数转字符串
     * @param mixed $number
     * @return string
     */
    function floatTransferString($number) {
        return number_format($number,FundsEnums::DecimalPlaces, '.','');
    }
}

if(!function_exists('pusher')){
    function pusher($contents, $key='')
    {
        $key = $key?:env('PUSHER_KEY');
        if(!$key){
            return null;
        }
        $url        = 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=' . $key;
        $payload    = ['msgtype' => 'text', 'text' => ['content'=>$contents]];
        Http::post($url, $payload);
    }
}


if(!function_exists('getFinalSql')){
    function getFinalSql($queryLog){
        foreach ($queryLog as $query) {
            $sql 		= str_replace('?', '%s', $query['query']);
            $bindings 	= array_map(function ($binding) {
                return is_numeric($binding) ? $binding : "'{$binding}'";
            }, $query['bindings']);
            $fullSql 	= vsprintf($sql, $bindings);
            echo $fullSql;
            echo "\n";
        }
    }
}
