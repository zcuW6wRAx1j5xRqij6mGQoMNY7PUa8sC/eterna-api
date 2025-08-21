<?php

namespace App\Http\Resources;

use App\Enums\SymbolEnums;
use App\Models\UserCollectionSymbol;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class SymbolFuturesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'symbol_id'=>$this->symbol_id,
            'binance_symbol'=>strtolower($this->symbol->binance_symbol ?? ''),
            'name'=>$this->symbol->name,
            'logo'=>$this->coin->logo ?? '',
            'base_asset'=>$this->symbol->base_asset,
            'quote_asset'=>$this->symbol->quote_asset,
            'digits'=>$this->symbol->digits,
            'status'=>$this->status,
            'is_recommend'=>$this->is_recommend ? true : false,
            'is_collection'=>UserCollectionSymbol::where('symbol_id', $this->id)->where('symbol_type', SymbolEnums::SymbolTypeFutures)->where('uid', $request->user()->id)->exists(),
        ];
    }
}
