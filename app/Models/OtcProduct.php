<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtcProduct extends Model
{
    protected $table = 'otc_product';

    public function symbolCoin() {
        return $this->belongsTo(SymbolCoin::class,'coin_id');
    }
}
