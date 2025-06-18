<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\ThirdpartyEnums;
use App\Http\Controllers\Api\ApiController;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Internal\Pay\Services\UdunService;
use Internal\Wallet\Actions\DepositCallback;
use Internal\Wallet\Actions\WithdrawCallback;

class ThirdpartyController extends ApiController {

    /**
     * u盾回调
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function udunCallback(Request $request, DepositCallback $depositCallback, WithdrawCallback $withdrawCallback) {
        Log::info('receive udun callback',[
            'data'=>$request->all(),
            'header'=>$request->headers,
        ]);

        $content = $request->all();
        $t = $content['timestamp'] ?? '';
        $n = $content['nonce'] ?? '';
        $s = $content['sign'] ?? '';
        $b = $content['body'] ?? '';

        if (!$t || !$n || !$s || !$b) {
            Log::error('failed to handle udunt callback , params incorrect',[
                'data'=>$request->all(),
                'header'=>$request->headers,
            ]);
            return $this->ok(true);
        }

        $isOk = (new UdunService)->checkSignature($b,$t, $n, $s);
        if (!$isOk) {
            Log::error('failed to handle udunt callback , data not safe',[
                'data'=>$request->all(),
                'header'=>$request->headers,
            ]);
            return $this->ok(true);
        }

        $l = Carbon::now()->subHours(2)->getTimestampMs();
        $r = Carbon::now()->getTimestampMs();
        if ($t < $l || $t > $r) {
            // 时间不正确
            Log::error('failed to handle udunt callback , error timestamp',[
                'data'=>$request->all(),
                'header'=>$request->headers,
            ]);
            return $this->ok(true);
        }
        $data = json_decode($b, true);
        $tradeType = $data['tradeType'] ?? 0;

        if (!$tradeType) {
            Log::error('failed to handle udunt callback , no complete data',[
                'data'=>$request->all(),
                'header'=>$request->headers,
            ]);
            return $this->ok(true);
        }
        switch ($tradeType) {
            case ThirdpartyEnums::UdunCallbackTypeDeposit:
                $depositCallback($data);
            break;
            case ThirdpartyEnums::UdunCallbackTypeWithdraw:
                $withdrawCallback($data);
            break;
            default:
                Log::error('failed to handle udunt callback , tradeType incorrect',[
                    'data'=>$request->all(),
                    'header'=>$request->headers,
                ]);
                return $this->ok(true);
            break;
        }
        return $this->ok(true);
    }
}
