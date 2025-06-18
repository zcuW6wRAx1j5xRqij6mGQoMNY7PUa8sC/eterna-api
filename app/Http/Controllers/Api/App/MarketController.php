<?php

namespace App\Http\Controllers\Api\App;

use App\Enums\CommonEnums;
use App\Enums\SymbolEnums;
use App\Http\Controllers\Api\ApiController;
use App\Models\SymbolCoin;
use App\Models\SymbolSpot;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Internal\Market\Actions\FuturesList;
use Internal\Market\Actions\Kline;
use Internal\Market\Actions\SpotList;
use Internal\Market\Actions\Symbol;
use Internal\Market\Actions\SymbolCollections;
use Internal\Market\Actions\SymbolDetail;
use Internal\Market\Payloads\QueryListPayload;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/** @package App\Http\Controllers\Api\App */
class MarketController extends ApiController {

    public function coins(Request $request) {
        return $this->ok(SymbolCoin::all());
    }

    /**
     * 首页推荐
     * @param Request $request
     * @param SpotList $spotList
     * @return JsonResponse
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function recommend(Request $request, SpotList $spotList) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
        ]);
        $payload = new QueryListPayload($request);
        $payload->isRecommend = CommonEnums::Yes;
        return $this->ok($spotList(new QueryListPayload($request)));
    }

    /**
     * 现货列表
     * @param Request $request
     * @param SpotList $spotList
     * @return JsonResponse
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function spot(Request $request, SpotList $spotList) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'keyword'=>'string',
        ]);
        return $this->ok($spotList(new QueryListPayload($request)));
    }

    /**
     * 合约交易对列表
     * @param Request $request
     * @param FuturesList $futuresList
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function futures(Request $request, FuturesList $futuresList) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'keyword'=>'string',
        ]);
        return $this->ok($futuresList(new QueryListPayload($request)));
    }

    /**
     * 收藏列表
     * @param Request $request
     * @param SymbolCollections $symbolCollections
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function myCollection(Request $request, SymbolCollections $symbolCollections) {
        $request->validate([
            'page'=>'numeric',
            'page_size'=>'numeric',
            'symbol_type'=>['required', Rule::in([SymbolEnums::SymbolTypeSpot,SymbolEnums::SymbolTypeFutures])],
        ]);
        return $this->ok($symbolCollections->list($request));
    }

    /**
     * 收藏/取消收藏
     * @param Request $request
     * @param SymbolCollections $symbolCollections
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws MassAssignmentException
     * @throws BindingResolutionException
     */
    public function collection(Request $request, SymbolCollections $symbolCollections) {
        $request->validate([
            'symbol_id'=>'required|numeric',
            'symbol_type'=>['required', Rule::in([SymbolEnums::SymbolTypeSpot,SymbolEnums::SymbolTypeFutures])],
        ]);
        return $this->ok($symbolCollections($request));
    }


    /**
     * 交易对详情
     * @param Request $request
     * @param Symbol $symbol
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function symbol(Request $request, SymbolDetail $symbolDetail) {
        $request->validate([
            'symbol_id'=>'required|numeric',
            'symbol_type'=>['required', Rule::in([SymbolEnums::SymbolTypeSpot,SymbolEnums::SymbolTypeFutures])],
        ]);
        return $this->ok($symbolDetail($request));
    }

    /**
     * 历史k线数据
     * @param Request $request
     * @param Kline $kline
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function klineHistory(Request $request,Kline $kline ) {
        $request->validate([
            'symbol_type'=>['required', Rule::in([SymbolEnums::SymbolTypeFutures,SymbolEnums::SymbolTypeSpot])],
            'symbol_id'=>'required|numeric',
            'interval'=>'required|string',
        ]);
        $data = $kline($request);
        return $this->ok($data);
    }

    /**
     * 查询所有交易对简单k线
     * @param Request $request 
     * @param Kline $kline 
     * @return JsonResponse 
     * @throws BadRequestException 
     * @throws BindingResolutionException 
     */
    public function allSymbolSimpleLine(Request $request, Kline $kline) {
        $request->validate([
            'symbol_type'=>['required', Rule::in([SymbolEnums::SymbolTypeFutures,SymbolEnums::SymbolTypeSpot])],
            'symbol_ids'=>['required','array']
        ]);
        $data = $kline->allSymbolSimpleKline($request->get('symbol_type'), $request->get('symbol_ids'));
        return $this->ok($data);
    }
}
