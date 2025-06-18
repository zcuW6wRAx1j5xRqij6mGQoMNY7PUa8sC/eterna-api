<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class UserDerivativelPosition extends Model
{
    protected $table = 'user_derivativel_positions';

    public function symbol() {
        return $this->belongsTo(Symbol::class,'symbol_id');
    }

    public function derivative() {
        return $this->belongsTo(DerivativesSymbol::class, 'derivative_id');
    }
}
