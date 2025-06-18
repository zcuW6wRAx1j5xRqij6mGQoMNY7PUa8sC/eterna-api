<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Symbol extends Model
{
    protected $table = 'symbols';

    protected $hidden = [
        'self_data'
    ];
    
    public function coin() {
        return $this->belongsTo(SymbolCoin::class,'coin_id');
    }
}