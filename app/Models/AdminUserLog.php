<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class AdminUserLog extends Model
{
    protected $table = 'admin_user_log';

    protected $casts = [
        'content' => 'array',
    ];
}
