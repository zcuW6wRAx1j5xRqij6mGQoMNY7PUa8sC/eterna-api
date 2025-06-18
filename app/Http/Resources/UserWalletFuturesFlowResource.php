<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserWalletFuturesFlowResource extends JsonResource
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
            'flow_type'=>$this->flow_type,
            'before_amount'=>$this->before_amount,
            'amount'=>$this->amount,
            'after_amount'=>$this->after_amount,
            'created_at'=>$this->created_at,
        ];
    }
}
