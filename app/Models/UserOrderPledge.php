<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class UserOrderPledge extends Model
{
    protected $table = 'user_order_pledge';

    public function user() {
        return $this->belongsTo(User::class,'uid');
    }
    public function symbolCoin() {
        return $this->belongsTo(SymbolCoin::class,'coin_id');
    }

}
