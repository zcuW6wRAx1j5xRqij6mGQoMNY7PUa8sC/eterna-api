<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class UserCollectionSymbol extends Model
{
    protected $table = 'user_collection_symbols';

    public function spot() {
        return $this->belongsTo(SymbolSpot::class,'symbol_id');
    }

    public function futures() {
        return $this->belongsTo(SymbolFutures::class,'symbol_id');
    }
}
