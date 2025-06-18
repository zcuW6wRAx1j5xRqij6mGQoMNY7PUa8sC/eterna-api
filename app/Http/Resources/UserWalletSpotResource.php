<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserWalletSpotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uid'=>$this->uid,
            'coin_name'=>$this->coin->name,
            'logo'=>$this->coin->logo,
            'coin_id'=>$this->coin_id,
            'amount'=>$this->amount,
            'lock_amount'=>$this->lock_amount,
            'usdt_value'=>$this->usdt_value,
        ];
    }
}
