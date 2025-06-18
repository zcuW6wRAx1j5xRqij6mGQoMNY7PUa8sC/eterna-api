<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserOrderPledgeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->symbolCoin->name,
            'uid'           => $this->uid,
            'status'        => $this->status,
            'created_at'    => $this->status=='hold'?$this->start_at:$this->created_at,
            'amount'        => $this->amount,
            'quota'         => $this->quota,
            'duration'      => $this->duration,
            'email'         => $this->user->email,
            'phone'         => $this->user->phone,
        ];
    }
}
