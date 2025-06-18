<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class PlatformWallet extends Model
{
    protected $table = 'platform_wallet';

    public function coin() {
        return $this->belongsTo(SymbolCoin::class,'coin_id');
    }
}
