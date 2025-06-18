<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserWalletAddress extends Model
{
    use SoftDeletes;
    
    protected $table = 'user_wallet_address';

    public function platform() {
        return $this->belongsTo(PlatformWallet::class,'platform_wallet_id');
    }
}
