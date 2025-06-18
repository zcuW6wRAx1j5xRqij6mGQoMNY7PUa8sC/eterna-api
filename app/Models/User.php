<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Exceptions\LogicException;
use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;

/** @package App\Models */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    // 默认用户token过期时间
    const DefaultTokenTTL = 60 * 60 * 24 * 30;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'trade_password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function level() {
        return $this->belongsTo(UserLevel::class);
    }

    public function parent() {
        return $this->belongsTo(User::class,'parent_id');
    }

    public function salesmanInfo() {
        return $this->belongsTo(AdminUser::class,'salesman');
    }

    private function makeTokenName() {
        $deviceType = request()->header('X-Device-Type', 'app');
        return "{$deviceType}_".md5(request()->userAgent());
    }

    public function logoutAllDevice() {
        return $this->tokens()->delete();
    }

    public function logout() {
        $tokenName = $this->makeTokenName();
        $this->tokens()->where('name', $tokenName)->delete();

        return $tokenName;
    }

    public function generateToken() {
        $tokenName = $this->logout();

        return $this->createToken($tokenName,['*'], Carbon::now()->addSeconds(self::DefaultTokenTTL))->plainTextToken;
    }


    /**
     * 资金锁定返回
     * @return void
     * @throws BindingResolutionException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws LogicException
     */
    public function checkFundsLock() {
        if ($this->funds_lock) {
            throw new LogicException(__('Your account is currently locked. To resolve this issue, please contact our customer support team.'));
        }
    }
}
