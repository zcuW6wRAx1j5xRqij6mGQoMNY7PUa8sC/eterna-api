<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class UserOrderFutures extends Model
{
    protected $table = 'user_order_futures';

    public function symbol() {
        return $this->belongsTo(Symbol::class,'symbol_id');
    }

    public function user() {
        return $this->belongsTo(User::class,'uid');
    }


    public function futures() {
        return $this->belongsTo(SymbolFutures::class,'futures_id');
    }
}
