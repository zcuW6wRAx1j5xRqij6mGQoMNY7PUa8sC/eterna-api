<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWithdraw extends Model
{
    protected $table = 'user_withdraw';

    public function user() {
        return $this->belongsTo(User::class,'uid');
    }

    protected $hidden = [
        'callback_raw',
        'admin_id',
    ];

    protected $casts = [
        'callback_raw'=>'array',
    ];

    public function coin() {
        return $this->belongsTo(SymbolCoin::class,'coin_id');
    }
}
