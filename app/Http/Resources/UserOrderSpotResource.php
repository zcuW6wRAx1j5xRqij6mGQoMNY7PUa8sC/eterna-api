<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserOrderSpotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (!isset($this->id)) {
            return [];
        }

        return [
            'id'=>$this->id,
            'uid'=>$this->uid,
            'name'=>isset($this->symbol)? ($this->symbol->name ?? ''):'',
            'logo'=>isset($this->symbol)?($this->symbol->coin->logo ?? ''):'',
            'spot_id'=>$this->spot_id,
            'symbol_id'=>$this->symbol_id,
            'side'=>$this->side,
            'trade_type'=>$this->trade_type,
            'price'=>$this->price,
            'match_price'=>$this->match_price,
            'match_time'=>$this->match_time,
            'quantity'=>$this->quantity,
            'volume'=>$this->volume,
            'trade_volume'=>$this->trade_volume,
            'trade_status'=>$this->trade_status,
            'created_at'=>$this->created_at,
            'updated_at'=>$this->updated_at,
        ];
    }
}
