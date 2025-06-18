<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserWithdrawResource extends JsonResource
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
            'order_no'=>$this->order_no,
            'coin_id'=>$this->coin_id,
            'coin_name'=>$this->coin->name,
            'coin_logo'=>$this->coin->logo,
            'coin_block'=>$this->coin->block,
            'receive_wallet_address'=>$this->receive_wallet_address,
            'amount'=>$this->amount,
            'fee'=>$this->fee,
            'audit_status'=>$this->audit_status,
            'status'=>$this->status,
            'reason'=>$this->reason,
            'created_at'=>$this->created_at,
            'updated_at'=>$this->updated_at,
        ];
    }
}
