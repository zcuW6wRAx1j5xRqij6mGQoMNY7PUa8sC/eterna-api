<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SymbolResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name'=>$this->name,
            'symbol'=>$this->symbol,
            'icon'=>$this->icon,
            'base_asset'=>$this->base_asset,
            'quote_asset'=>$this->quote_asset,
            'digits'=>$this->digits,
            'buy_spread'=>$this->buy_spread,
            'sell_spread'=>$this->sell_spread,
            'sort'=>$this->sort,
        ];
    }
}
