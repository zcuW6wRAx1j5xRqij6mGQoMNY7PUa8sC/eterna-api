<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDeposit extends Model
{
    protected $table = 'user_deposit';

    public function user() {
        return $this->belongsTo(User::class,'uid');
    }

    public function coin() {
        return $this->belongsTo(SymbolCoin::class,'coin_id');
    }

    public function address() {
        return $this->belongsTo(UserWalletAddress::class,'wallet_address','address');
    }

    protected $hidden = [
        'callback_raw'
    ];

    protected $casts = [
        'callback_raw'=>'array',
    ];
}
