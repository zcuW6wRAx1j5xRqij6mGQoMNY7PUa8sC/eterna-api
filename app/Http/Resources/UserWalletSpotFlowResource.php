<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserWalletSpotFlowResource extends JsonResource
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
            'coin_id'=>$this->coin_id,
            'coin_name'=> $this->coin->name ?? '',
            'coin_logo'=>$this->coin->logo??'',
            'flow_type'=>$this->flow_type,
            'before_amount'=>$this->before_amount,
            'amount'=>$this->amount,
            'after_amount'=>$this->after_amount,
            'created_at'=>$this->created_at,
            'updated_at'=>$this->updated_at,
        ];
    }
}
