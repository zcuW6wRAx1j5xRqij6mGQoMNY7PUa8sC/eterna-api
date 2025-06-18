<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id()->from(8088801);

            $table->string('avatar')->nullable()->comment('头像');
            $table->string('name')->nullable()->comment('名称');
            $table->char('phone_code',10)->nullable()->comment('国际区号');
            $table->string('phone')->nullable()->comment('手机号');
            $table->string('email')->nullable()->comment('邮箱');
            $table->string('password')->comment('登录密码');
            $table->string('trade_password')->nullable()->comment('交易密码');
            $table->tinyInteger('is_verified_identity')->default(0)->comment('是否通过实名认证 0 否 1是');
            $table->string('register_ip')->comment('注册IP');
            $table->string('register_device')->comment('注册信息');
            $table->string('latest_login_ip')->nullable()->comment('最新登录IP');
            $table->timestamp('latest_login_time')->nullable()->comment('最新登录时间');
            $table->string('parent_id')->default(0)->comment('上级ID');
            $table->bigInteger('salesman')->default(0)->comment('业务员ID');
            $table->bigInteger('relation_id')->default(0)->comment('业务员后台的账号ID');
            $table->string('invite_code')->nullable()->comment('邀请码');
            $table->text('remark')->nullable()->comment('备注');
            $table->string('lang')->default('en')->comment('语言');
            $table->tinyInteger('status')->default(1)->comment('状态 0 关闭 1开启');
            $table->bigInteger('level_id')->default(0)->comment('等级ID');
            $table->integer('punch_rewards')->default(0)->comment('签到积分');
            $table->integer('funds_lock')->default(0)->comment('资金锁 1 锁定 0 未锁定');
            $table->integer('role_type')->default(1)->comment('角色类型 1 : 普通用户 2: 内部账户 3 : 测试账户');

            $table->rememberToken();
            $table->timestamps();

            $table->index('invite_code');
            $table->index('phone');
            $table->index('email');
            $table->index('salesman');
            $table->index('relation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
