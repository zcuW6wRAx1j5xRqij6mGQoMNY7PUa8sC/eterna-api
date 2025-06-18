<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtcOrder extends Model
{
    protected $table = 'otc_order';

    public function user() {
        return $this->belongsTo(User::class,'uid');
    }
    public function product() {
        return $this->belongsTo(OtcProduct::class,'product_id');
    }

}
