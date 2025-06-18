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
        Schema::create('admin_user', function (Blueprint $table) {
            $table->id();

            $table->char('nickname')->comment('昵称');
            $table->char('username')->unique()->comment('用户名');
            $table->char('password')->comment('密码');
            $table->char('avatar')->nullable()->comment('头像');
            $table->integer('role_id')->default(0)->comment('角色分组id');
            $table->integer('parent_id')->unsigned()->default(0)->comment('上级ID');
            $table->integer('operator')->default(0)->comment('创建管理员id');
            $table->char('desc')->default(0)->comment('备注/描述信息');
            $table->string('invite_code', 18)->default('')->comment('业务员邀请码');
            $table->timestamp('last_login_time')->nullable()->comment('上次登陆时间');
            $table->tinyInteger('status')->default(1)->comment('状态 0关闭 1开启');

            $table->index('username');
            $table->softDeletes();
            $table->timestamps();
            $table->comment('后台管理员表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_user');
    }
};
