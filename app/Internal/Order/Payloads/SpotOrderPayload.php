<?php

namespace Internal\Order\Payloads;

use App\Enums\CoinEnums;
use App\Enums\CommonEnums;
use App\Enums\FundsEnums;
use App\Enums\OrderEnums;
use App\Exceptions\LogicException;
use App\Models\Symbol;
use App\Models\SymbolSpot;
use App\Models\User;
use App\Models\UserOrderSpot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Internal\Market\Actions\FetchSymbolQuote;

class SpotOrderPayload {

    public User $user;
    // 现货ID
    public int $spotId;
    // 交易方向
    public string $side;
    // 交易类型
    public string $tradeType;
    // 交易货币数量
    public $quantity;
    // 报价
    public $price;
    // 市场价
    public $marketPrice;
    // 最终计算价格
    public $matchPrice;
    // 点差
    public $spread;
    // 交易量 交易货币数量
    public $volume;
    // 交易额 报价货币数量
    public $tradeVolume;
    // 交易货币
    public $baseAsset;
    // 交易货币数量
    public $baseAssetQuantity;
    // 交易货币ID
    public $baseAssetCoinId;
    // 报价货币
    public $quoteAsset;
    // 报价货币数量
    public $quoteAssetQuantity;
    // 报价货币ID
    public $quoteAssetCoinId;

    public Symbol $symbol;
    public SymbolSpot $symbolSpot;

    public function parseFromRequest(Request $request)
    {
        $this->side = $request->get('side');
        $this->quantity = number_format(abs($request->get('quantity',0)), FundsEnums::DecimalPlaces,'.','');
        $this->price = abs($request->get('price',0));
        $this->tradeType = $request->get('trade_type');
        $this->spotId = $request->get('spot_id');
        $this->user = $request->user();


        if ($this->quantity <= 0) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }
        $spot = SymbolSpot::find($this->spotId);
        if (!$spot) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }
        if ($spot->status !== CommonEnums::Yes) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }
        $symbol = $spot->symbol ?? null;
        if (!$symbol || $symbol === null) {
            throw new LogicException(__('Whoops! Something went wrong'));
        }

        if ($this->tradeType == OrderEnums::TradeTypeLimit && !$this->price) {
            throw new LogicException(__('Incorrect price'));
        }

        $this->symbolSpot = $spot;
        $this->symbol = $symbol;
        
        $this->tradeVolume = $this->quantity;

        // 计算点差
        $this->spread = $this->symbolSpot->buy_spread;
        $this->matchPrice = 0;

        if ($this->tradeType == OrderEnums::TradeTypeMarket) {
             // 获取最新市价
             $this->marketPrice = (new FetchSymbolQuote)($symbol->symbol);
             if (!$this->marketPrice) {
                 Log::error('failed to create spot order : no quote',[
                     'symbolId'=>$symbol->id,
                 ]);
                 throw new LogicException(__('Whoops! Something went wrong'));
             }

            // $this->marketPrice = BinanceService::getInstance()->fetchSymbolSpotQuote($symbol->binance_symbol);
            // if (!$this->marketPrice) {
            //     Log::error('failed to create spot order : no quote',[
            //         'symbolId'=>$symbol->id,
            //     ]);
            //     throw new LogicException(__('Whoops! Something went wrong'));
            // }
            $this->price = $this->marketPrice;
            $this->matchPrice = $this->spread ? bcadd($this->marketPrice, $this->spread, FundsEnums::DecimalPlaces) : $this->marketPrice;
            $this->volume = bcdiv($this->tradeVolume, $this->matchPrice, FundsEnums::DecimalPlaces);
        } else {
            $this->volume = 0;
        }


        $this->baseAsset = $this->symbol->base_asset;
        $this->quoteAsset = $this->symbol->quote_asset;

        if ($this->side == OrderEnums::SideSell) {
            [$this->baseAsset, $this->quoteAsset] = [$this->quoteAsset, $this->baseAsset];

            $this->spread = $this->symbolSpot->sell_spread;
            $this->matchPrice = $this->spread ? bcsub($this->marketPrice, $this->spread, FundsEnums::DecimalPlaces) : $this->marketPrice;

            // 卖单 , 提交来的是 BTC 数量
            $this->volume = $this->quantity;
            $this->tradeVolume = bcmul($this->matchPrice , $this->quantity, FundsEnums::DecimalPlaces);
        }
        return $this;
    }


    public function parseFromOrder(UserOrderSpot $order) {
        $this->user = User::find($order->uid);
        $this->side = $order->side;
        $this->quantity = $order->quantity;
        $this->price = $order->price;
        $this->marketPrice = $order->market_price;
        $this->tradeType = $order->trade_type;
        $this->spotId = $order->spot_id;
        $this->symbolSpot = $order->spot;
        $this->symbol = $order->symbol;
        $this->volume = $order->volume;
        $this->spread = $order->spread;
        $this->baseAsset = $order->base_asset;
        $this->quoteAsset = $order->quote_asset;

        $this->baseAssetQuantity = $order->quantity;
        $this->baseAssetCoinId = $order->symbol->coin_id;
        $this->quoteAssetQuantity = bcmul($this->quantity, $this->price, FundsEnums::DecimalPlaces) ;
        $this->quoteAssetCoinId = CoinEnums::DefaultUSDTCoinID;

        if ($this->side == OrderEnums::SideSell) {
            [$this->baseAsset, $this->quoteAsset] = [$this->quoteAsset, $this->baseAsset];
            [$this->baseAssetQuantity, $this->quoteAssetQuantity] = [$this->quoteAssetQuantity, $this->baseAssetQuantity];
            [$this->baseAssetCoinId, $this->quoteAssetCoinId] = [$this->quoteAssetCoinId, $this->baseAssetCoinId];

            $this->spread = - $order->spot->sell_spread;
        }
        return $this;
    }
}
