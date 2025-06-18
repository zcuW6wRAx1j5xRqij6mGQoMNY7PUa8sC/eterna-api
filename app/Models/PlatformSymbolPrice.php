<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class PlatformSymbolPrice extends Model
{
    protected $table = 'platform_symbol_price';

    public function symbol() {
        return $this->belongsTo(Symbol::class, 'symbol_id');
    }
}
