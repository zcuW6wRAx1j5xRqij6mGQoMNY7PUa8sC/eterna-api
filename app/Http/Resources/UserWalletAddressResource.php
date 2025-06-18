<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserWalletAddressResource extends JsonResource
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
            'coin_id'=>$this->platform->coin_id,
            'coin_name'=>$this->platform->coin->name,
            'coin_logo'=>$this->platform->coin->logo,
            'coin_block'=>$this->platform->block,
            'address'=>$this->address,
        ];
    }
}
