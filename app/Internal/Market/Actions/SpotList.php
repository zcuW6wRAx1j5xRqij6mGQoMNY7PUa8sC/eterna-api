<?php

namespace Internal\Market\Actions;

use App\Enums\CommonEnums;
use App\Http\Resources\SymbolSpotCollection;
use App\Models\Symbol;
use App\Models\SymbolSpot;
use Internal\Market\Payloads\QueryListPayload;

class SpotList {

    public function __invoke(QueryListPayload $payload)
    {
        $query = SymbolSpot::with(['coin','symbol'])->where('status', CommonEnums::Yes);
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
        return new SymbolSpotCollection($query->orderBy('sort')->paginate($payload->pageSize));
    }
}
