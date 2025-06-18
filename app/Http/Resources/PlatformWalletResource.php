<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformWalletResource extends JsonResource
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
            'coin_id'=>$this->coin->id,
            'coin_name'=>$this->coin->name,
            'coin_logo'=>$this->coin->logo,
            'block'=>$this->block,
        ];
    }
}
