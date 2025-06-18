<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWalletSpot extends Model
{
    protected $table = 'user_wallet_spot';

    public function coin() {
        return $this->belongsTo(SymbolCoin::class,'coin_id');
    }
}
