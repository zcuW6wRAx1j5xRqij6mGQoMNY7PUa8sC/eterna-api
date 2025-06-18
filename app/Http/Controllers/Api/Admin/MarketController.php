<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AdminLogTypeEnums;
use App\Enums\CommonEnums;
use App\Enums\SymbolEnums;
use App\Exceptions\LogicException;
use App\Http\Controllers\Api\ApiController;
use App\Jobs\StartFakePrice;
use App\Jobs\StopFakePrice;
use App\Models\AdminUserLog;
use App\Models\PlatformSymbolPrice;
use App\Models\Symbol;
use App\Models\SymbolCoin;
use App\Models\SymbolFutures;
use App\Models\SymbolSpot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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
class MarketController extends ApiController
{

    public function coins(Request $request)
    {
        $data = SymbolCoin::query()->orderBy('sort')->get();
        return $this->ok($data);
    }

    /**
     * 简单symbol 数据, 仅限后台筛选使用
     * @param Request $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function simpleSymbols(Request $request)
    {
        $data = Symbol::select(['id', 'name', 'symbol', 'binance_symbol'])->where('quote_asset', 'usdt')->where('status', CommonEnums::Yes)->get();
        return $this->ok($data);
    }

    /**
     * 简单合约列表 - 用于后台查询
     * @param Request $request
     * @return JsonResponse
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function simpleFutures(Request $request)
    {
        $futures = SymbolFutures::with(['symbol' => function ($query) {
            $query->select(['id', 'name', 'symbol']);
        }])->select(['id', 'symbol_id'])->where('status', CommonEnums::Yes)->get();
        return $this->ok($futures);
    }

    /**
     * 交易对列表
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function symbols(Request $request)
    {
        $request->validate([
            'page' => 'numeric',
            'page_size' => 'numeric',
            'status' => ['nullable', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'name' => 'nullable|string',
        ]);

        $status = $request->get('status', null);
        $name = $request->get('name', '');
        $query = Symbol::query();
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($name) {
            $query->where('name', 'like', '%' . $name . '%');
        }

        $data = $query->orderBy('created_at')->paginate($request->get('page_size'), ['*'], null, $request->get('page'));
        return $this->ok(listResp($data));
    }

    public function fakePrice(Request $request)
    {
        $request->validate([
            'symbol_type' => ['nullable', Rule::in([SymbolEnums::SymbolTypeFutures, SymbolEnums::SymbolTypeSpot])],
            'symbol'=>'nullable|string',
        ]);

        $st = $request->get('symbol_type');

        $symbol = $request->get('symbol', '');
        $query = PlatformSymbolPrice::with(['symbol']);
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
        $id = $request->get('id');
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
     * @return JsonResponse 
     * @throws BadRequestException 
     * @throws InvalidArgumentException 
     * @throws LogicException 
     * @throws BindingResolutionException 
     */
    public function getAirCoinPrice(Request $request) {
        $request->validate([
            'id'=>'required|numeric',
        ]);

        $air = PlatformSymbolPrice::with('symbol')->where('id', $request->get('id'))->first();
        if (!$air) {
            throw new LogicException('数据不正确');
        }

        $price = 0;
        if ($air->symbol_type == SymbolEnums::SymbolTypeSpot) {
            $price= (new FetchSymbolQuote)($air->symbol->symbol);
        } else {
            $price = (new FetchSymbolFuturesQuote)($air->symbol->symbol);
        }
        return $this->ok($price);
    }

    public function setFakePrice(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric',
            'start_time' => 'required|numeric',
            // 'duration_time' => 'required|numeric',
            'price' => 'required|numeric',
        ]);

        DB::transaction(function () use ($request) {
            $id = $request->get('id');
            $price = $request->get('price');
            $now = Carbon::now();
            $startTime = $request->get('start_time');
            if ($startTime <= 0) {
                throw new LogicException('开始时间必须大于当前时间');
            }
            $startTime = intval($startTime);

            $before = 0;
            $cfg = PlatformSymbolPrice::with('symbol')->where('id',$id)->first();
            if (!$cfg) {
                throw new LogicException('数据不正确');
            }
            if ($cfg->status == CommonEnums::Yes) {
                throw new LogicException('当前任务正在进行, 请先需求后再操作');
            }
            // $cfg->duration_time = $request->get('duration_time');
            $cfg->duration_time = 1000;
            $cfg->start_time = $now->addMinutes($startTime)->toDateTimeString();
            $cfg->status = CommonEnums::Yes;
            $cfg->task_id = generateUuid();

            $before = $cfg->fake_price;
            $cfg->fake_price = $price;
            $cfg->save();

            $log = new AdminUserLog();
            $log->admin_id = $request->user()->id;
            $log->log_type = AdminLogTypeEnums::LogTypeSettingFake;
            $log->content = [
                'id'=>$cfg->id,
                'before' => $before,
                'setting' => $price,
            ];
            $log->ip = $request->ip();
            $log->save();

            (new SetFakePrice)($cfg, $startTime);

            $jobStart = Carbon::now()->addMinutes($startTime);
            // $jobStopTime = $jobStart->copy()->addMinutes($cfg->duration_time + 2);
            // StopFakePrice::dispatch($cfg)->delay($jobStopTime);

            // $jobStart = Carbon::now()->addSeconds($startTime->diffInSeconds($now));
            // StartFakePrice::dispatch($cfg)->delay($jobStart);
            return true;
        });
        return $this->ok(true);
    }

    /**
     * 修改交易对信息
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function modifySymbols(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric',
            'status' => ['nullable', Rule::in([CommonEnums::Yes, CommonEnums::No])],
        ]);

        $status = $request->get('status', null);
        $symbol = Symbol::findOrFail($request->get('id'));

        if ($status !== null) {
            $symbol->status = $status;
        }
        $symbol->save();
        return $this->ok(true);
    }

    /**
     * 现货交易对
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function spotSymbols(Request $request)
    {
        $request->validate([
            'page' => 'numeric',
            'page_size' => 'numeric',
            'status' => ['nullable', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'keyword' => 'nullable|string',
            'is_recommend' => ['nullable', Rule::in([CommonEnums::Yes, CommonEnums::No])],
        ]);


        $status = $request->get('status', null);
        $keyword = $request->get('keyword', '');
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
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function modifySpotSymbol(Request $request)
    {
        $request->validate([
            'id' => 'numeric',
            'coin_id' => 'required|numeric',
            'status' => ['required', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'is_recommend' => ['required', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'sort' => 'required|numeric',
            'symbol_id' => 'required|numeric',
            'buy_spread' => 'numeric',
            'sell_spread' => 'numeric',
        ]);
        $id = $request->get('id', 0);
        $coinId = $request->get('coin_id');
        $model = $id ? SymbolSpot::findOrFail($id) : new SymbolSpot();
        $model->status = $request->get('status');
        $model->is_recommend = $request->get('is_recommend');
        $model->coin_id = $coinId;
        $model->sort = $request->get('sort');
        $model->symbol_id = $request->get('symbol_id');
        $model->buy_spread = $request->get('buy_spread', 0);
        $model->sell_spread = $request->get('sell_spread', 0);
        $model->save();
        return $this->ok(true);
    }

    /**
     * 合约交易对列表
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    public function DerivativeSymbols(Request $request)
    {
        $request->validate([
            'page' => 'numeric',
            'page_size' => 'numeric',
            'status' => ['nullable', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'keyword' => 'nullable|string',
        ]);


        $status = $request->get('status', null);
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
     * @param Request $request
     * @return JsonResponse
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    public function modifyDervativeSymbol(Request $request)
    {
        $request->validate([
            'id' => 'numeric',
            'status' => ['required', Rule::in([CommonEnums::Yes, CommonEnums::No])],
            'sort' => 'required|numeric',
            'symbol_id' => 'required|numeric',
            'buy_spread' => 'numeric',
            'sell_spread' => 'numeric',
        ]);
        $id = $request->get('id', 0);
        $model = $id ? SymbolFutures::findOrFail($id) : new SymbolFutures();
        $model->status = $request->get('status');
        $model->sort = $request->get('sort');
        $model->symbol_id = $request->get('symbol_id');
        $model->buy_spread = $request->get('buy_spread', 0);
        $model->sell_spread = $request->get('sell_spread', 0);
        $model->save();
        return $this->ok(true);
    }
}
