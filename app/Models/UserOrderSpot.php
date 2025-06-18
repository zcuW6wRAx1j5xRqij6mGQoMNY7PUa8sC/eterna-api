<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class UserOrderSpot extends Model
{
    protected $table = 'user_order_spot';

    public function symbol() {
        return $this->belongsTo(Symbol::class,'symbol_id');
    }

    public function user() {
        return $this->belongsTo(User::class,'uid');
    }

    public function spot() {
        return $this->belongsTo(SymbolSpot::class,'spot_id');
    }
}
