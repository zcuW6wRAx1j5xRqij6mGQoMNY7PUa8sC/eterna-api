<?php

namespace Internal\Market\Actions;

use App\Enums\CommonEnums;
use App\Http\Resources\SymbolFuturesCollection;
use App\Models\Symbol;
use App\Models\SymbolFutures;
use Internal\Market\Payloads\QueryListPayload;

class FuturesList {

    public function __invoke(QueryListPayload $payload)
    {
        $query = SymbolFutures::with(['symbol','coin'])->where('status', CommonEnums::Yes);
        if ($payload->keyword !== null) {
            $symbolInfo = Symbol::select(['id'])->where('symbol','like','%'.$payload->keyword.'%')->get();
            if ($symbolInfo->isEmpty()) {
                return [];
            }
            $query->whereIn('symbol_id', $symbolInfo->pluck('id')->toArray());
        }
        if ($payload->isRecommend === CommonEnums::Yes) {
            $query->where('is_recommend', CommonEnums::Yes);
        }
        return new SymbolFuturesCollection($query->orderBy('sort')->paginate($payload->pageSize));
    }
}
