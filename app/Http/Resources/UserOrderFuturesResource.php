<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserOrderFuturesResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'order_code'        => $this->order_code,
            'uid'               => $this->uid,
            'margin'            => $this->margin,
            'margin_ratio'      => $this->margin_ratio,
            'margin_type'       => $this->margin_type,
            'name'              => $this->symbol->name,
            'symbol'            => $this->symbol->symbol,
            'lots'              => $this->lots,
            'logo'              => $this->symbol->coin->logo ?? '',
            'futures_id'        => $this->futures_id,
            'symbol_id'         => $this->symbol_id,
            'side'              => $this->side,
            'sl'                => $this->sl,
            'tp'                => $this->tp,
            'trade_type'        => $this->trade_type,
            'price'             => $this->price,
            'profit'            => $this->profit,
            'profit_ratio'      => $this->profit_ratio,
            'match_price'       => $this->match_price,
            'match_time'        => $this->match_time,
            'volume'            => $this->volume,
            'trade_volume'      => $this->trade_volume,
            'leverage'          => $this->leverage,
            'open_price'        => $this->open_price,
            'open_fee'          => $this->open_fee,
            'close_price'       => $this->close_price,
            'close_time'        => $this->close_time,
            'close_type'        => $this->close_type,
            'close_fee'         => $this->close_fee,
            'force_close_price' => $this->force_close_price,
            'trade_status'      => $this->trade_status,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
