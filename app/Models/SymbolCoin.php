<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class SymbolCoin extends Model
{
    protected $table = 'symbol_coins';

    public function symbol() {
        return $this->hasOne(Symbol::class,'coin_id');
    }
}
