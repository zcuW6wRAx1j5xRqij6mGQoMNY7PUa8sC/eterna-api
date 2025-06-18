<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class IeoSymbol extends Model
{
    protected $table = 'ieo_symbols';

    public function symbol() {
        return $this->belongsTo(Symbol::class,'symbol_id');
    }

    public function coin() {
        return $this->belongsTo(SymbolCoin::class,'coin_id');
    }
}
