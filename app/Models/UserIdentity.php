<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class UserIdentity extends Model
{
    protected $table = 'user_identity';

    public function user() {
        return $this->belongsTo(User::class,'uid');
    }

    public function country() {
        return $this->hasOne(PlatformCountry::class,'id','country_id');
    }
}
