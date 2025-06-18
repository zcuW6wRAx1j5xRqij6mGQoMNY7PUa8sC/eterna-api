<?php

namespace App\Internal\Order\Payloads;

use App\Enums\CommonEnums;
use App\Enums\ConfigEnums;
use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Exceptions\LogicException;
use App\Models\PledgeCoinConfig;
use App\Models\Symbol;
use App\Models\SymbolFutures;
use App\Models\User;
use App\Models\UserOrderPledge;
use App\Models\UserOrderSpot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Internal\Common\Services\ConfigService;
use Internal\Market\Actions\FetchSymbolFuturesQuote;
use Internal\Market\Actions\FetchSymbolQuote;
use Internal\Market\Services\BinanceService;

class PledgeOrderPayload {
    public User $user;
    // 质押币种ID
    public int $coinId;
    // 市场价
    public $marketPrice;
    public $amount;
    public $USDCNum;
    public $duration;

    public function __construct()
    {
        $this->USDCNum = CommonEnums::PledgeUSDNum;
    }

    public function parseFromRequest(Request $request)
    {
        $this->user     = $request->user();
        $this->coinId   = $request->get('coin_id');
        $this->duration = $request->get('duration');


        //查询是否配置该货币允许质押
        $pledge =  PledgeCoinConfig::where('coin_id', $this->coinId)->first();
        if (!$pledge) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }
        if ($pledge->status !== CommonEnums::Yes) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }

        $symbol = Symbol::where('coin_id', $this->coinId)->first();
        // 获取最新市价
        $this->marketPrice = (new FetchSymbolQuote)($symbol->symbol);
        if (!$this->marketPrice) {
            Log::error('failed to create spot order : no quote',[
                'symbolId'=>$symbol->id,
            ]);
            throw new LogicException(__('Whoops! Something went wrong'));
        }

        $this->USDCNum      = CommonEnums::PledgeUSDNum;
        $this->amount       = bcdiv($this->USDCNum, $this->marketPrice, 4);
        return $this;
    }


    public function parseFromOrder(UserOrderPledge $order) {
        return $this;
    }
}
