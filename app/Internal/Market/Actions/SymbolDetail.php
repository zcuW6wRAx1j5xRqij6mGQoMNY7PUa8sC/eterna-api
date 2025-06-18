<?php

namespace Internal\Market\Actions;

use App\Enums\SymbolEnums;
use App\Http\Resources\SymbolFuturesResource;
use App\Http\Resources\SymbolSpotResource;
use App\Models\SymbolFutures;
use App\Models\SymbolSpot;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;

class SymbolDetail {

    public function __invoke(Request $request)
    {
        $symbolType = $request->get('symbol_type');
        $symbolId = $request->get('symbol_id');

        $data = null;
        switch ($symbolType) {
            case SymbolEnums::SymbolTypeFutures:
                $data = new SymbolFuturesResource(
                    SymbolFutures::query()->with(['coin','symbol','userCollection'])->where('id', $symbolId)->first()
                );
            break;
            case SymbolEnums::SymbolTypeSpot:
                $data = new SymbolSpotResource(
                    SymbolSpot::with(['coin','symbol','userCollection'])->where('id', $symbolId)->first()
                );
            break;
        }
        return $data;
    }
}

