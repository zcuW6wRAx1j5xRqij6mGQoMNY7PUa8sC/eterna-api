<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWalletSpotFlow extends Model
{
    protected $table = 'user_wallet_spot_flow';

    public function coin() {
        return $this->belongsTo(SymbolCoin::class,'coin_id');
    }
}
