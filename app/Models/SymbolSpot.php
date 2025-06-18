<?php

namespace App\Models;

use App\Enums\SymbolEnums;
use Illuminate\Database\Eloquent\Model;

class SymbolSpot extends Model
{
    protected $table = 'symbol_spots';

    public function coin() {
        return $this->belongsTo(SymbolCoin::class,'coin_id');
    }

    public function symbol() {
        return $this->belongsTo(Symbol::class,'symbol_id');
    }

    public function userCollection() {
        return $this->belongsTo(UserCollectionSymbol::class,'id','symbol_id')->where('symbol_type', SymbolEnums::SymbolTypeSpot);
    }
}
