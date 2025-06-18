<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWalletFutures extends Model
{
    protected $table = 'user_wallet_futures';

    public function coin() {
        return $this->belongsTo(SymbolCoin::class,'coin_id');
    }
}
