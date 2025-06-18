<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class UserLevel extends Model
{
    const FirstaLevelName = 'L0';
    protected $table = 'user_level';

    public static function getFirstLevel() {
        $level = UserLevel::where('name',self::FirstaLevelName)->first();
        if (!$level) {
            return 0;
        }
        return $level->id;
    }
}
