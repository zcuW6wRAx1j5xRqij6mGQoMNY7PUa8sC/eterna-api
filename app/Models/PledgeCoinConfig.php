<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PledgeCoinConfig extends Model
{
    protected $table = 'pledge_coin_config';

    public function symboCoin() {
        return $this->belongsTo(SymbolCoin::class,'coin_id');
    }
}
