<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class IeoOrder extends Model
{
    protected $table = 'ieo_orders';

    public function user() {
        return $this->belongsTo(User::class,'uid');
    }

    public function ieo() {
        return $this->belongsTo(IeoSymbol::class,'ieo_id');
    }
}
