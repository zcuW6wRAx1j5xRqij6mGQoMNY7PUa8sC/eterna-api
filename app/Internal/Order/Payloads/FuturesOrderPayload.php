<?php

namespace Internal\Order\Payloads;

use App\Enums\CommonEnums;
use App\Enums\ConfigEnums;
use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Exceptions\LogicException;
use App\Models\Symbol;
use App\Models\SymbolFutures;
use App\Models\User;
use App\Models\UserOrderSpot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Internal\Common\Services\ConfigService;
use Internal\Market\Actions\FetchSymbolFuturesQuote;
use Internal\Market\Services\BinanceService;

class FuturesOrderPayload {
    public User $user;
    // 合约ID
    public int $futuresId;
    // 保证金类型
    public string $marginType;
    // 杠杆
    public $leverage;
    // 交易方向
    public string $side;
    // 交易类型
    public string $tradeType;
    // 报价
    public $price;
    // 最终成交价格
    public $matchPrice;
    // 市场价
    public $marketPrice;
    // 点差
    public $spread;
    //手数
    public $lots;
    // 交易量 交易货币数量
    public $volume;
    // 交易额 USDT 数量
    public $tradeVolume;
    // 保证金
    public $margin;
    // 手续费 
    public $fee;
    // 止损
    public $sl;
    // 止盈
    public $tp;

    public Symbol $symbol;
    public SymbolFutures $SymbolFutures;

    public function parseFromRequest(Request $request)
    {
        $this->side = $request->get('side');
        $this->price = abs($request->get('price',0));
        $this->tradeType = $request->get('trade_type');
        $this->marginType = $request->get('margin_type');
        $this->leverage = $request->get('leverage');
        $this->lots = number_format(abs($request->get('lots',0)), FundsEnums::DecimalPlaces,'.','');
        $this->futuresId = $request->get('futures_id');
        $this->sl = abs($request->get('sl',0));
        $this->tp = abs($request->get('tp',0));
        $this->user = $request->user();

        if ($this->lots <= 0) {
            throw new LogicException(__('Incorrect trade volume'));
        }

        $this->margin = bcmul($this->lots, OrderEnums::DefaultLotsTradevalue, FundsEnums::DecimalPlaces);
        $this->tradeVolume = bcmul($this->margin, $this->leverage, FundsEnums::DecimalPlaces);

        $futures =  SymbolFutures::find($this->futuresId);
        if (!$futures) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }
        if ($futures->status !== CommonEnums::Yes) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }
        $symbol = $futures->symbol ?? null;
        if (!$symbol || $symbol === null) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }

        if ($this->tradeType == OrderEnums::TradeTypeLimit && !$this->price) {
            throw new LogicException(__('Incorrect price'));
        }

        if ($this->tradeType == OrderEnums::TradeTypeMarket) {
            // 获取最新市价
            $this->marketPrice = (new FetchSymbolFuturesQuote)($symbol->symbol);
            if (!$this->marketPrice) {
                Log::error('failed to create spot order : no quote',[
                    'symbolId'=>$symbol->id,
                ]);
                throw new LogicException(__('Whoops! Something went wrong'));
            }
            $this->price = $this->marketPrice;
        }

        // 扣去交易手续费
        $fee = ConfigService::getIns()->fetch(ConfigEnums::PlatformConfigFuturesOpenFee, 0);
        if ($fee) {
            $fee = bcdiv($fee, 100, FundsEnums::DecimalPlaces);
            $this->fee = bcmul($this->tradeVolume, $fee, FundsEnums::DecimalPlaces);
        }

        $this->SymbolFutures = $futures;
        $this->symbol = $symbol;

         // 2024/11/17 没有点差
         // 收取手续费
        // $this->spread = $this->SymbolFutures->buy_spread;
        // $this->matchPrice = $this->spread ? bcadd($this->marketPrice, $this->spread, FundsEnums::DecimalPlaces) : $this->marketPrice;


        // if ($this->side == OrderEnums::SideSell) {
        //     $this->spread = $this->SymbolFutures->sell_spread;
        //     $this->matchPrice = $this->spread ? bcsub($this->marketPrice, $this->spread, FundsEnums::DecimalPlaces) : $this->marketPrice; 
        // }

        $this->matchPrice = $this->marketPrice;
        return $this;
    }


    public function parseFromOrder(UserOrderSpot $order) {
        return $this;
    }
}
