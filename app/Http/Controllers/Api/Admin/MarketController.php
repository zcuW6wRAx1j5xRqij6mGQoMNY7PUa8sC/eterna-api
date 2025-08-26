<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\BotTask;
use App\Enums\CommonEnums;
use App\Enums\SymbolEnums;
use App\Exceptions\LogicException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Internal\Market\Services\InfluxDB;
use App\Http\Controllers\Api\ApiController;
use App\Models\PlatformSymbolPrice;
use App\Models\Symbol;
use App\Models\SymbolCoin;
use App\Models\SymbolFutures;
use App\Models\SymbolSpot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use App\Internal\Tools\Services\GbmPathService;
use App\Internal\Tools\Services\KlineAggregatorService;
use App\Internal\Tools\Services\BotTask as ServicesBotTask;
use App\Http\Requests\Api\Admin\ChangeKlineTypeRequest;
use App\Http\Requests\Api\Admin\CreateMarketTaskRequest;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Internal\Market\Actions\FetchSymbolFuturesQuote;
use Internal\Market\Actions\FetchSymbolQuote;
use Internal\Market\Actions\SetFakePrice;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use InvalidArgumentException;

/** @package App\Http\Controllers\Api\Admin */
class MarketController extends ApiController {
    
    public function coins(Request $request)
    {
        $data = SymbolCoin::query()->orderBy('sort')->get();
        return $this->ok($data);
    }
    
    /**
     * 简单symbol 数据, 仅限后台筛选使用
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function simpleSymbols(Request $request)
    {
        $request->validate([
            'keyword' => 'nullable|string',
        ]);
        
        $keyword = $request->get('keyword');
        
        $query = Symbol::select(['id', 'name', 'symbol', 'binance_symbol'])->where('status', CommonEnums::Yes);
        if ($keyword) {
            $query->where('symbol', 'like', '%' . $keyword . '%');
        }
        $data = $query->get();
        return $this->ok($data);
    }
    
    /**
     * 简单合约列表 - 用于后台查询
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function simpleFutures(Request $request)
    {
        $futures = SymbolFutures::with([
            'symbol' => function ($query) {
                $query->select(['id', 'name', 'symbol']);
            },
        ])->select(['id', 'symbol_id'])->where('status', CommonEnums::Yes)->get();
        return $this->ok($futures);
    }
    
    /**
     * 交易对列表
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function symbols(Request $request)
    {
        $request->validate([
            'page'      => 'numeric',
            'page_size' => 'numeric',
            'status'    => ['nullable', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'name'      => 'nullable|string',
        ]);
        
        $status = $request->get('status', null);
        $name   = $request->get('name', '');
        $query  = Symbol::query();
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($name) {
            $query->where('name', 'like', '%' . $name . '%');
        }
        
        $data = $query->orderBy('created_at')->paginate($request->get('page_size'), ['*'], null, $request->get('page'));
        return $this->ok(listResp($data, function ($items) {
            $i = $items['items'] ?? [];
            if ($i) {
                $items['items'] = collect($i)->map(function ($item) {
                    $item->_self_data = $item->self_data ?? '';
                    return $item;
                });
            }
            return $items;
        }));
    }
    
    public function fakePrice(Request $request)
    {
        $request->validate([
            // 'symbol_type' => ['nullable', Rule::in([SymbolEnums::SymbolTypeFutures, SymbolEnums::SymbolTypeSpot])],
            'symbol' => 'nullable|string',
        ]);
        
        $st = $request->get('symbol_type');
        
        $symbol = $request->get('symbol', '');
        $query  = PlatformSymbolPrice::with(['symbol']);
        if ($st) {
            $query->where('symbol_type', $st);
        }
        if ($symbol) {
            $query->whereHas('symbol', function ($query) use ($symbol) {
                $query->where('symbol', 'like', '%' . $symbol . '%');
            });
        }
        
        $data = $query->get();
        if ($data->isEmpty()) {
            return $this->ok([]);
        }
        return $this->ok($data);
    }
    
    public function cancelFakePrice(Request $request, SetFakePrice $setFakePrice)
    {
        $request->validate([
            'id' => 'required|numeric',
        ]);
        $id  = $request->get('id');
        $cfg = PlatformSymbolPrice::find($id);
        if (!$cfg) {
            throw new LogicException('数据不正确');
        }
        if ($cfg->status !== CommonEnums::Yes) {
            return $this->ok(true);
        }
        $setFakePrice->handleCancel($cfg->id);
        return $this->ok(true);
    }
    
    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function getAirCoinPrice(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric',
        ]);
        
        $air = PlatformSymbolPrice::with('symbol')->where('id', $request->get('id'))->first();
        if (!$air) {
            throw new LogicException('数据不正确');
        }
        
        $price = 0;
        if ($air->symbol_type == SymbolEnums::SymbolTypeSpot) {
            $price = (new FetchSymbolQuote)($air->symbol->symbol);
        } else {
            $price = (new FetchSymbolFuturesQuote)($air->symbol->symbol);
        }
        return $this->ok($price);
    }
    
    public function setFakePrice(Request $request)
    {
        $request->validate([
            'id'            => 'required|numeric',
            'duration_time' => 'required|numeric',
            'price'         => 'required|numeric',
        ]);
        
        $id           = $request->get('id');
        $price        = $request->get('price');
        $durationTime = $request->get('duration_time');
        if ($durationTime <= 0) {
            throw new LogicException('持续时间必须大于0');
        }
        
        
        $cfg = PlatformSymbolPrice::with('symbol')->where('id', $id)->first();
        if (!$cfg) {
            throw new LogicException('数据不正确');
        }
        $symbol = $cfg->symbol;
        if (!$symbol) {
            throw new LogicException('数据不正确');
        }
        $currentPrice = (new FetchSymbolQuote)($symbol->symbol);
        if (!$currentPrice || $currentPrice <= 0) {
            throw new LogicException('执行失败, 没有获取到当前价格');
        }
        
        $startTime = Carbon::now()->addSeconds(2);
        $endTime   = $startTime->copy()->addSeconds($durationTime);
        
        // 检测是否与机器人执行时间冲突
        $rows = BotTask::query()
                       ->where('symbol_id', $symbol->id)
                       ->where('status', CommonEnums::Yes)
                       ->get();
        foreach ($rows as $row) {
            $start = Carbon::parse($row['start_at'], config('app.timezone'))->setTimezone('UTC');
            $end   = Carbon::parse($row['end_at'], config('app.timezone'))->setTimezone('UTC');
            if ($startTime->between($start, $end) || $endTime->between($start, $end)) {
                throw new LogicException('执行失败, 时间冲突');
            }
        }
        
        $open = $close = $currentPrice;
        $high = 0;
        $low  = 0;
        
        $d = bcsub($price, $currentPrice, 8);
        if ($d == 0) {
            throw new LogicException('执行失败, 目标价格不正确 , 不能当前价格一致');
        }
        
        if ($d > 0) {
            $high = $price;
            $low  = $currentPrice;
        } else {
            $low  = $price;
            $high = $currentPrice;
        }
        
        $service = new ServicesBotTask();
        $result  = $service->createTask(
            $request->user()->id,
            $symbol->id,
            'spot',
            $open,
            $high,
            $low,
            $close,
            $startTime,
            $endTime,
        );
        if ($result) {
            return $this->fail($result);
        }
        
        return $this->ok(true);
        
        
        // $before = 0;
        // $cfg    = PlatformSymbolPrice::with('symbol')->where('id', $id)->first();
        // if (!$cfg) {
        //     throw new LogicException('数据不正确');
        // }
        // if ($cfg->status == CommonEnums::Yes) {
        //     throw new LogicException('当前任务正在进行, 请先需求后再操作');
        // }
        // // $cfg->duration_time = $request->get('duration_time');
        // $cfg->duration_time = 1000;
        // $cfg->start_time    = $now->addMinutes($startTime)->toDateTimeString();
        // $cfg->status        = CommonEnums::Yes;
        // $cfg->task_id       = generateUuid();
        
        // $before          = $cfg->fake_price;
        // $cfg->fake_price = $price;
        // $cfg->save();
        
        // $log           = new AdminUserLog();
        // $log->admin_id = $request->user()->id;
        // $log->log_type = AdminLogTypeEnums::LogTypeSettingFake;
        // $log->content  = [
        //     'id'      => $cfg->id,
        //     'before'  => $before,
        //     'setting' => $price,
        // ];
        // $log->ip       = $request->ip();
        // $log->save();
        
        // (new SetFakePrice)($cfg, $startTime);
        
        // $jobStart = Carbon::now()->addMinutes($startTime);
        // // $jobStopTime = $jobStart->copy()->addMinutes($cfg->duration_time + 2);
        // // StopFakePrice::dispatch($cfg)->delay($jobStopTime);
        
        // // $jobStart = Carbon::now()->addSeconds($startTime->diffInSeconds($now));
        // // StartFakePrice::dispatch($cfg)->delay($jobStart);
        // return true;
        return $this->ok(true);
    }
    
    /**
     * 修改交易对信息
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function modifySymbols(Request $request)
    {
        $request->validate([
            'id'          => 'nullable|numeric',
            'base_asset'  => 'required|string',
            'quote_asset' => 'required|string',
            'self_data'   => ['required', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'status'      => ['nullable', Rule::in([CommonEnums::Yes, CommonEnums::No])],
        ]);
        
        DB::transaction(function () use ($request) {
            $id         = $request->get('id');
            $baseAsset  = $request->get('base_asset');
            $quoteAsset = $request->get('quote_asset');
            $selfData   = $request->get('self_data');
            $status     = $request->get('status', null);
            
            $symbolModel = null;
            
            if ($id) {
                $symbolModel = Symbol::find($id);
                if (!$symbolModel) {
                    throw new LogicException('数据不正确');
                }
            } else {
                $symbolModel = new Symbol();
            }
            
            $name   = strtoupper($baseAsset) . '/' . strtoupper($quoteAsset);
            $symbol = strtolower($baseAsset) . strtolower($quoteAsset);
            if ($selfData != CommonEnums::Yes) {
                $binanceSymbol = strtoupper($baseAsset) . strtoupper($quoteAsset);
            }
            
            $symbolExists = Symbol::where('symbol', $symbol)->exists();
            if ($id && $symbolExists && $symbolExists->id != $id) {
                throw new LogicException('交易对已存在');
            }
            if ($symbolExists) {
                throw new LogicException('交易对已存在');
            }
            
            $symbolCoin = SymbolCoin::where('name', strtoupper($baseAsset))->first();
            if (!$symbolCoin) {
                $symbolCoin        = new SymbolCoin();
                $symbolCoin->name  = strtoupper($baseAsset);
                $symbolCoin->block = strtoupper($baseAsset);
                $symbolCoin->save();
            }
            
            $symbolModel->name           = $name;
            $symbolModel->symbol         = $symbol;
            $symbolModel->coin_id        = $symbolCoin->id;
            $symbolModel->binance_symbol = $binanceSymbol ?? null;
            $symbolModel->self_data      = $selfData;
            $symbolModel->status         = $status;
            $symbolModel->save();
            return true;
        });
        return $this->ok(true);
    }
    
    /**
     * 现货交易对
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function spotSymbols(Request $request)
    {
        $request->validate([
            'page'         => 'numeric',
            'page_size'    => 'numeric',
            'status'       => ['nullable', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'keyword'      => 'nullable|string',
            'is_recommend' => ['nullable', Rule::in([CommonEnums::Yes, CommonEnums::No])],
        ]);
        
        
        $status      = $request->get('status', null);
        $keyword     = $request->get('keyword', '');
        $isRecommend = $request->get('is_recommend', null);
        
        $query = SymbolSpot::with('symbol');
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($keyword) {
            $s = Symbol::select(['id'])->where('name', 'like', '%' . $keyword . '%')->get();
            if ($s->isEmpty()) {
                return $this->ok(listResp(null));
            }
            $query->whereIn('symbol_id', $s->pluck('id')->toArray());
        }
        if ($isRecommend !== null) {
            $query->where('is_recommend', $isRecommend);
        }
        
        $data = $query->orderBy('sort')->paginate($request->get('page_size'), ['*'], null, $request->get('page'));
        return $this->ok(listResp($data));
    }
    
    /**
     * 修改现货交易对
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function modifySpotSymbol(Request $request)
    {
        $request->validate([
            'id'           => 'numeric',
            'coin_id'      => 'required|numeric',
            'status'       => ['required', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'is_recommend' => ['required', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'sort'         => 'required|numeric',
            'symbol_id'    => 'required|numeric',
            'buy_spread'   => 'numeric',
            'sell_spread'  => 'numeric',
        ]);
        $id                  = $request->get('id', 0);
        $coinId              = $request->get('coin_id');
        $model               = $id ? SymbolSpot::findOrFail($id) : new SymbolSpot();
        $model->status       = $request->get('status');
        $model->is_recommend = $request->get('is_recommend');
        $model->coin_id      = $coinId;
        $model->sort         = $request->get('sort');
        $model->symbol_id    = $request->get('symbol_id');
        $model->buy_spread   = $request->get('buy_spread', 0);
        $model->sell_spread  = $request->get('sell_spread', 0);
        $model->save();
        return $this->ok(true);
    }
    
    /**
     * 合约交易对列表
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function DerivativeSymbols(Request $request)
    {
        $request->validate([
            'page'      => 'numeric',
            'page_size' => 'numeric',
            'status'    => ['nullable', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'keyword'   => 'nullable|string',
        ]);
        
        
        $status  = $request->get('status', null);
        $keyword = $request->get('keyword', '');
        
        $query = SymbolFutures::with('symbol');
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($keyword) {
            $s = Symbol::select(['id'])->where('name', 'like', '%' . $keyword . '%')->get();
            if ($s->isEmpty()) {
                return $this->ok(listResp(null));
            }
            $query->whereIn('symbol_id', $s->pluck('id')->toArray());
        }
        
        
        $data = $query->orderBy('sort')->paginate($request->get('page_size'), ['*'], null, $request->get('page'));
        return $this->ok(listResp($data));
    }
    
    /**
     * 修改合约交易对
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function modifyDervativeSymbol(Request $request)
    {
        $request->validate([
            'id'          => 'numeric',
            'status'      => ['required', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'sort'        => 'required|numeric',
            'symbol_id'   => 'required|numeric',
            'buy_spread'  => 'numeric',
            'sell_spread' => 'numeric',
        ]);
        $id                 = $request->get('id', 0);
        $model              = $id ? SymbolFutures::findOrFail($id) : new SymbolFutures();
        $model->status      = $request->get('status');
        $model->sort        = $request->get('sort');
        $model->symbol_id   = $request->get('symbol_id');
        $model->buy_spread  = $request->get('buy_spread', 0);
        $model->sell_spread = $request->get('sell_spread', 0);
        $model->save();
        return $this->ok(true);
    }
    
    public function BotTaskList(Request $request)
    {
        $request->validate([
            'page'      => 'numeric',
            'page_size' => 'numeric',
            'status'    => ['nullable', Rule::in([CommonEnums::Yes, CommonEnums::No])],
        ]);
        
        $status = $request->get('status', null);
        $query  = BotTask::query()->with(['symbol']);
        if ($status !== null) {
            $query->where('status', $status);
        }
        
        $data = $query->orderByDesc('id')->paginate($request->get('page_size'), ['*'], null, $request->get('page'));
        $data = listResp($data);
//        foreach ($data['items'] as &$item) {
//            if ($item['status'] == CommonEnums::Yes && Carbon::now(config('app.timezone'))->isAfter(Carbon::parse($item['end_at'], 'UTC')
//                                                                                                          ->setTimezone(config('app.timezone')))
//            ) {
//                $item['status'] = 3;
//            }
//
//            $item['start_at'] = date('Y-m-d H:i:s', strtotime('+8 hour', strtotime($item['start_at'])));
//            $item['end_at']   = date('Y-m-d H:i:s', strtotime('+8 hour', strtotime($item['end_at'])));
//        }
        
        return $this->ok($data);
    }
    
    /**
     * 预览K线图数据
     *
     * 该方法用于根据用户请求生成预览版K线图数据，并将其存储到缓存中以供后续展示
     * 它会首先验证交易对信息的正确性，然后通过模拟生成K线图数据，并将其缓存起来
     *
     * @param CreateMarketTaskRequest $request 用户请求，包含生成K线图所需的各种参数
     *
     * @return JsonResponse 返回预览K线图数据的响应
     * @throws BindingResolutionException
     */
    public function previewKline(CreateMarketTaskRequest $request): JsonResponse
    {
        // 从请求中获取参数
        $coinID     = $request->input('coin_id');
        $coinType   = $request->input('coin_type');
        $open       = $request->input('open', 0);
        $targetHigh = $request->input('high');
        $targetLow  = $request->input('low');
        $close      = $request->input('close');
        $startTime  = $request->input('start_time');
        $endTime    = $request->input('end_time');
        $sigma      = $request->input('sigma', 0.0003);
        
        try {
            // 构建查询条件以验证交易对信息
            $where = [
                'id'     => $coinID,
                'status' => CommonEnums::Yes,
            ];
            // 获取交易对信息
            $info = Symbol::where($where)->first();
            // 如果交易对不存在，则记录错误日志并返回错误响应
            if (!$info) {
                Log::error('Coin Not Found');
                return $this->fail(__('Coin Not Found'));
            }
            $symbol = strtoupper($info->symbol);
            $open   = $open <= 0 ? 0.0001 : $open;
            $data   = GbmPathService::generateCandles(
                (float)$open,
                (float)$close,
                $startTime,
                $endTime,
                (float)$targetHigh,
                (float)$targetLow,
                $sigma
            );
            
            $ttl = 30 * 60;
            $uid = $request->user()->id;
            // 构建缓存键名
            $key = sprintf(config('kline.preview_key'), $uid, $symbol);
            // 将模拟的K线图数据存储到缓存中
            $result = Cache::set($key, json_encode($data), $ttl);
            
            // 如果缓存设置失败，则记录错误日志并返回错误响应
            if (!$result) {
                Log::error('Cache Set Failed');
                return $this->fail('Create Failed');
            }
            $taskKey = sprintf(config('kline.preview_task_key'), $uid, $symbol);
            $task    = [
                'symbol_id'   => $coinID,
                'symbol_type' => 'spot',
                'open'        => $open,
                'high'        => $targetHigh,
                'low'         => $targetLow,
                'close'       => $close,
                'sigma'       => $sigma,
                'start_at'    => Carbon::parse($startTime, config('app.timezone'))->setTimezone('UTC')->toDateTimeString(),
                'end_at'      => Carbon::parse($endTime, config('app.timezone'))->setTimezone('UTC')->toDateTimeString(),
            ];
            
            Cache::set($taskKey, json_encode($task), $ttl);
            $interval = config('kline.interval', "1m");
            $candles  = KlineAggregatorService::aggregate($data, [$interval]);
            // 返回成功响应，包含模拟的K线图数据
            $candles = $candles[$interval];
            $minutes = count($candles);
            return $this->ok(['duration' => $minutes, 'candles' => $candles]);
        } catch (\Exception $e) {
            // 捕获异常，记录错误日志并返回错误响应
            Log::error(sprintf('PreView Kline Error: %s(%s): %s', $e->getFile(), $e->getLine(), $e->getMessage()));
            return $this->fail('Failed');
        }
    }
    
    /**
     * 修改K线类型
     *
     * 本函数用于根据用户请求更改K线图表的类型用户需要指定币种ID、币种类型以及目标K线类型
     * 函数首先验证请求参数的正确性，然后从数据库中获取币种信息，接着从缓存中获取K线数据，
     * 最后对数据进行处理以匹配用户请求的K线类型
     *
     * @param ChangeKlineTypeRequest $request 包含用户请求数据的请求对象
     *
     * @return JsonResponse 返回处理结果的JSON响应
     * @throws BindingResolutionException
     */
    public function changeKlineType(ChangeKlineTypeRequest $request): JsonResponse
    {
        // 从请求中获取币种ID、币种类型和K线类型，K线类型默认为'1m'
        $coinID   = $request->input('coin_id');
        $interval = $request->input('type', '1m');
        
        try {
            // 构建查询条件，包括币种ID和状态为启用
            $where = [
                'id'     => $coinID,
                'status' => CommonEnums::Yes,
            ];
            
            // 根据查询条件获取币种信息
            $info = Symbol::where($where)->first();
            
            // 如果没有找到对应的币种信息，返回错误提示
            if (!$info) {
                Log::error('Coin Not Found');
                return $this->fail('Coin Not Found');
            }
            
            // 获取当前用户ID
            $uid = $request->user()->id;
            
            // 生成缓存键名
            $symbol = strtoupper($info->symbol);
            // 根据用户ID、币种符号和币种类型生成缓存键名
            $key = sprintf(config('kline.preview_key'), $uid, $symbol);
            
            // 从缓存中获取K线数据
            $data = Cache::get($key);
            
            // 如果缓存中没有数据，返回错误提示
            if (!$data) {
                // 数据不存在，请重新生成
                Log::error('Data Not Found, Please Re-Generate');
                return $this->fail('Data Not Found, Please Re-Generate');
            }
            
            $candles = KlineAggregatorService::aggregate(json_decode($data, true), [$interval]);
            
            // 如果处理后的数据为空，返回错误提示
            if (!$candles) {
                Log::error('Data is Empty');
                return $this->fail('Data is Empty');
            }
            
            // 返回成功响应，包含模拟的K线图数据
            $candles = $candles[$interval];
            return $this->ok($candles);
        } catch (\Exception $e) {
            Log::error("Change Kline Type Error: {$e->getMessage()}}");
            // 如果发生异常，返回错误提示
            return $this->fail('Failed');
        }
    }
    
    public function createNewBotTask(Request $request, ServicesBotTask $service): JsonResponse
    {
        $result = (new InfluxDB('market_spot'))->writeData('dddusdc', '1d', [
            [
                'tl' => (time()-5*86400) * 1000,
                'c'  => 400.01,
                'h'  => 410.01,
                'l'  => 390.01,
                'o'  => 390.01,
                'v'  => 10000,
            ],
        ]);
        dd($result);
        return $this->ok();
        $coinID     = $request->input('coin_id');
        $open       = $request->input('open');
        $targetHigh = $request->input('high');
        $targetLow  = $request->input('low');
        $close      = $request->input('close');
        $startTime  = $request->input('start_time');
        $endTime    = $request->input('end_time');
        $sigma      = $request->input('sigma', 0.0003);
        $unit       = $request->input('unit', '1m');
        $symbol     = Symbol::query()->where('id', $coinID)->value('symbol');
        $service->generateHistoryData(
            $symbol,
            $open,
            $targetHigh,
            $targetLow,
            $close,
            $startTime,
            $endTime,
            $sigma,
            8,
            $unit
        );
        
        return $this->ok();
    }
    
    /**
     * 创建新的机器人任务
     *
     * 本函数用于处理创建新的市场任务请求，根据用户提供的信息和系统配置，
     * 生成并保存机器人任务，同时缓存相关数据以供后续使用
     *
     * @param Request $request 用户提交的创建市场任务请求，包含任务相关参数
     *
     * @return JsonResponse 返回任务创建结果，成功或失败
     * @throws BindingResolutionException
     */
    public function NewBotTask(Request $request, ServicesBotTask $servicesBotTask): JsonResponse
    {
        // 获取请求中的币种ID和计算明天的开始和结束时间
        $coinID = $request->get('coin_id');
        
        // 开始数据库事务，确保数据一致性
        DB::beginTransaction();
        
        try {
            // 查询币种信息，确保币种存在且状态为有效
            $where      = [
                'id'     => $coinID,
                'status' => CommonEnums::Yes,
            ];
            $symbolInfo = Symbol::where($where)->first();
            if (!$symbolInfo) {
                Log::error('Coin Not Found');
                return $this->fail('Coin Not Found');
            }
            
            // 获取币种符号，并转换为大写
            $symbol = strtoupper($symbolInfo->symbol);
            // 获取当前用户ID
            $uid = $request->user()->id;
            // 根据用户ID和币种符号生成缓存键名，尝试获取缓存数据
            $taskKey = sprintf(config('kline.preview_task_key'), $uid, $symbol);
            // 尝试获取缓存数据
            $task = Cache::get($taskKey);
            // 如果缓存数据不存在，返回错误提示
            if (!$task) {
                Log::error('Task Not Found');
                return $this->fail('Task Not Found');
            }
            // 解析缓存数据
            $task = json_decode($task, true);
            
            // 根据用户ID和币种符号生成缓存键名，尝试获取缓存数据
            $key  = sprintf(config('kline.preview_key'), $uid, $symbol);
            $data = Cache::get($key);
            $data = $data ? json_decode($data, true) : [];
            if (!$data) {
                Log::error('Data not found or not parsed correctly');
                return $this->fail('Data not found or not parsed correctly');
            }
            $everySecondPrice = [];
            foreach ($data as $item) {
                $everySecondPrice[$item['timestamp'] / 1000] = $item['close'];
            }
            // 如果没有数据，则返回错误响应
            if (empty($everySecondPrice)) {
                Log::error('No Data');
                return $this->fail('Create Failed');
            }
            
            // 创建新的机器人任务实例并填充数据
            $row = array_merge([
                'status'  => CommonEnums::Yes,
                'creator' => $uid,
            ], $task);
            
            // 检测是否与机器人执行时间冲突
            $newTaskStart = Carbon::parse($row['start_at'], config('app.timezone'))->setTimezone('UTC');
            $newTaskEndAt = Carbon::parse($row['end_at'], config('app.timezone'))->setTimezone('UTC');
            $histories    = BotTask::query()
                                   ->where('symbol_id', $symbolInfo->id)
                                   ->where('status', CommonEnums::Yes)
                                   ->get();
            foreach ($histories as $history) {
                $start = Carbon::parse($history['start_at'], config('app.timezone'))->setTimezone('UTC');
                $end   = Carbon::parse($history['end_at'], config('app.timezone'))->setTimezone('UTC');
                if ($newTaskStart->between($start, $end) || $newTaskEndAt->between($start, $end)) {
                    throw new LogicException('执行失败, 时间冲突');
                }
            }
            
            // 尝试保存机器人任务，如果失败则回滚事务并记录日志
            $task = BotTask::create($row);
            if (!$task) {
                DB::rollBack();
                Log::error('Create Bot Task Failed');
                return $this->fail('Failed');
            }
            
            // 生成队列键名，并尝试将数据缓存到新生成的队列键中，如果失败则回滚事务并记录日志
            $queueKey  = sprintf(config('kline.queue_key'), $symbol);
            $cacheData = RedisMarket()->get($queueKey);
            $cacheData = $cacheData ? json_decode($cacheData, true) : [];
            $cacheData = $cacheData ?: [];
            
            // 生成队列数据
            $queueData = $cacheData ? array_replace($cacheData, $everySecondPrice) : $everySecondPrice;
            if (!$queueData) {
                DB::rollBack();
                Log::error('Data not cached correctly');
                return $this->fail('Data not cached correctly' . json_encode($data));
            }
            
            // 尝试将数据缓存到队列中，如果失败则回滚事务并记录日志
            $result = RedisMarket()->set($queueKey, json_encode($queueData));
            if (!$result) {
                DB::rollBack();
                Log::error('Add Bot Task Failed');
                return $this->fail('Failed');
            }
            
            $servicesBotTask->newTask($task);
            
            // 删除原始缓存数据，提交事务，并返回成功响应
            Cache::delete($key);
            Cache::delete($taskKey);
            DB::commit();
            
            return $this->ok();
        } catch (\Exception $e) {
            // 捕获异常，回滚事务，并记录错误日志
            DB::rollBack();
            Log::error('Create Bot Task Failed:' . $e->getMessage());
            return $this->fail('Failed');
        }
    }
    
    /**
     * 修改日常涨跌幅度
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function changeFloat(Request $request, ServicesBotTask $servicesBotTask)
    {
        $request->validate([
            'bound'   => 'required|numeric',
            'coin_id' => 'nullable|numeric',
        ]);
        
        $where  = [
            'id'     => $request->get('coin_id', 2756),
            'status' => CommonEnums::Yes,
        ];
        $symbol = Symbol::query()->where($where)->value('symbol');
        if (!$symbol) {
            Log::error('Coin Not Found');
            return $this->fail('Coin Not Found');
        }
        
        // 获取币种符号，并转换为大写
        $symbol = strtoupper($symbol);
        $servicesBotTask->changeFloat($symbol, $request->get('bound'));
        
        return $this->ok(true);
    }
    
    /**
     * 删除机器人行情任务
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function DeleteBotTask(Request $request, ServicesBotTask $servicesBotTask)
    {
        $request->validate([
            'id' => 'required|numeric',
        ]);
        
        $bot = BotTask::find($request->get('id'));
        if (!$bot) {
            return $this->fail('Bot Task Not Found');
        }
        $bot->delete();
        
        $servicesBotTask->stopTask($bot);
        
        return $this->ok(true);
    }
    
    /**
     * 中止机器人行情任务
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function CancelBotTask(Request $request, ServicesBotTask $servicesBotTask)
    {
        $request->validate([
            'id' => 'required|numeric',
        ]);
        
        DB::beginTransaction();;
        try {
            $task          = BotTask::find($request->get('id'));
            $task->status  = 4;
            $task->updater = $request->user()->id;
            $task->save();
            $servicesBotTask->stopTask($task);
            
            DB::commit();
            return $this->ok(true);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->fail(false);
        }
    }
}
