<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;


class AdminUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // 默认用户token过期时间
    const DefaultTokenTTL = 60 * 60 * 24;

    protected $table = 'admin_user';

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $guarded = [];


    protected $casts = [
    ];

    public function logout() {
        return $this->tokens()->delete();
    }

    public function role()
    {
        return $this->belongsTo(AdminRole::class, 'id', 'role_id');
    }

    public function hasPermission($roleId, $url)
    {
        return AdminRelation::join('admin_menu', 'admin_menu.id', '=', 'admin_relation.menu_id')
                ->where(['role_id'=>$roleId, 'url'=>$url])->count() == 1;
    }

    public function generateToken() {
        $this->logout();
        return $this->createToken($this->id, ['*'], Carbon::now()->addSeconds(self::DefaultTokenTTL))->plainTextToken;
    }

    public function parent() {
        return $this->belongsTo(AdminUser::class,'parent_id');
    }

    public function sub() {
        return $this->hasMany(AdminUser::class,'parent_id', 'id');
    }

    public static function generateInviteCode()
    {
        for ($i = 0; $i < 50; $i++) {
            $code = '';
            for($i = 0; $i < 6; $i++){
                $code .= strval(rand(0, 9));
            }

            if (!self::where('invite_code', $code)->exists()) {
                return $code;
            }
        }
        return '';
    }

}
