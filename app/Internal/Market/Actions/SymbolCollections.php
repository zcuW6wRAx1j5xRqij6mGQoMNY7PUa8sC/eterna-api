<?php

namespace Internal\Market\Actions;

use App\Enums\SymbolEnums;
use App\Http\Resources\SymbolFuturesResource;
use App\Http\Resources\SymbolSpotResource;
use App\Models\SymbolFutures;
use App\Models\SymbolSpot;
use App\Models\UserCollectionSymbol;
use Illuminate\Http\Request;

class SymbolCollections {

    public function __invoke(Request $request)
    {
        $symbolType = $request->get('symbol_type');
        $symbolId = $request->get('symbol_id');
        $data = UserCollectionSymbol::query()->where('uid', $request->user()->id)->where('symbol_type', $symbolType)->where('symbol_id', $symbolId)->first();
        if ($data) {
            $data->delete();
            return true;
        }
        $model = new UserCollectionSymbol();
        $model->uid = $request->user()->id;
        $model->symbol_type = $symbolType;
        $model->symbol_id = $symbolId;
        $model->save();
        return true;
    }

    public function list(Request $request) {
        $symbolType = $request->get('symbol_type');
        $ids = UserCollectionSymbol::query()->where('uid', $request->user()->id)->where('symbol_type', $symbolType)->get();
        if ($ids->isEmpty()) {
            return [];
        }
        $data = null;
        switch ($symbolType) {
            case SymbolEnums::SymbolTypeFutures:
                $data = SymbolFuturesResource::collection(SymbolFutures::with(['coin','symbol'])->whereIn('id', $ids->pluck('symbol_id')->toArray())->get());
            break;
            case SymbolEnums::SymbolTypeSpot:
                $data = SymbolSpotResource ::collection(
                    SymbolSpot::with(['coin','symbol'])->whereIn('id', $ids->pluck('symbol_id')->toArray())->get()
                );
            break;
        }
        return $data;
    }

}
