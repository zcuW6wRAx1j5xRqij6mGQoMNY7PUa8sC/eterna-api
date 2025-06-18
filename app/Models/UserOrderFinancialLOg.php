<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class UserOrderFinancialLog extends Model
{
    protected $table = 'user_order_financial_log';

    public function order()
    {
        return $this->belongsTo(UserOrderFinancial::class, 'order_id');
    }
}
