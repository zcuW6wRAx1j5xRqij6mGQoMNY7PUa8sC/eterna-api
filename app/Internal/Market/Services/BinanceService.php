<?php

namespace Internal\Market\Services;

use App\Enums\AirCoinsEnums;
use Binance\Spot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinanceService {

    private static $ins = null;

    private $binance = null;

    private function __construct()
    {
        $this->binance = new Spot([
            'baseURL'=>'https://data-api.binance.vision'
        ]);
    }

    public static function getInstance() {
        if (self::$ins === null) {
            self::$ins = new self();
        }
        return self::$ins;
    }

    // binance服务器状态
    public function serviceStatus() {
        return $this->binance->ping() === [];
    }

    // binance服务器时间
    public function serviceTime() {
        $resp = $this->binance->time();
        $time = $resp['serverTime'] ?? '';
        if (!$time) {
            Log::error('同步binance 时间失败',[
                'resp'=>$resp,
            ]);
        }
        return $time;
    }

    // 获取交易对
    public function fetchSymbols() {
        $resp = $this->binance->exchangeInfo();
        $symbols = $resp['symbols'] ?? [];
        if (!$symbols) {
            Log::error('同步Binance 交易对失败',[
                'resp'=>$resp,
            ]);
            return [];
        }
        return $symbols;
    }

    /**
     * 获取现货报价
     * @param string $symbol
     * @return mixed
     */
    public function fetchSymbolSpotQuote(string $symbol) {
        $realSymbol = $symbol;
        if (in_array($symbol, array_keys(AirCoinsEnums::CoinsMap)) ) {
            $realSymbol = AirCoinsEnums::CoinsMap[$symbol] ?? '';
        }
        if (!$realSymbol) {
            $realSymbol = $symbol;
        }

        $resp = $this->binance->tickerPrice([
            'symbol'=>$realSymbol,
        ]);

        $symbolName = $resp['symbol'] ?? '';
        $price = $resp['price'] ?? '';
        if (!$symbolName || !$price) {
            Log::error('获取binance报价失败',[
                'resp_symbol'=>$symbolName,
                'symbol'=>$realSymbol,
                'price'=>$price,
            ]);
            return '0';
        }
        if ($symbolName != $realSymbol) {
            Log::error('获取binance报价失败 : 返回的symbol不一致',[
                'resp_symbol'=>$symbolName,
                'symbol'=>$realSymbol,
                'price'=>$price,
            ]);
            return '0';
        }
        return $price;
    }

    /**
     * 获取合约报价
     * @param string $symbol 
     * @return mixed 
     */
    public function fetchSymbolFuturesQuote(string $symbol) {
        $realSymbol = $symbol;
        if (in_array($symbol, array_keys(AirCoinsEnums::CoinsMap)) ) {
            $realSymbol = AirCoinsEnums::CoinsMap[$symbol] ?? '';
        }
        if (!$realSymbol) {
            $realSymbol = $symbol;
        }

        $url = "https://fapi.binance.com/fapi/v2/ticker/price?symbol=%s";
        $response = Http::get(sprintf($url,$realSymbol));
        if ( ! $response->ok()) {
            Log::error('获取binance报价失败',[
                'symbol'=>$symbol,
                'resp'=>$response->json()
            ]); 
            return '0';
        }

        $data = $response->json();
        $price = $data['price'] ?? '0';
        $receiveSymbol = $data['symbol'] ?? '';
        if ($realSymbol != $receiveSymbol) {
            Log::error('获取binance报价失败 : 返回的symbol不一致',[
                'symbol'=>$realSymbol,
                'resp'=>$response->json()
            ]);
            return '0'; 
        }
        return $price;
    }


}

