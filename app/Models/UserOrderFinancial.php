<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class UserOrderFinancial extends Model
{
    protected $table = 'user_order_financial';

    public function financial()
    {
        return $this->belongsTo(Financial::class, 'financial_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'uid');
    }
}