<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDepositResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'coin_id'=>$this->coin_id,
            'coin_name'=>$this->coin->name,
            'coin_logo'=>$this->coin->coin_logo,
            'wallet_address'=>$this->wallet_address,
            'amount'=>$this->amount,
            'usdt_value'=>$this->usdt_value,
            'status'=>$this->status,
            'created_at'=>$this->created_at,
            'updated_at'=>$this->updated_at,
        ];
    }
}
