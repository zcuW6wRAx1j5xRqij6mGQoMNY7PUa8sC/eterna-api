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
        Schema::create('platform_active_support', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('uid')->comment('用户UID');
            $table->decimal('rewards',20,8)->comment('奖励金额 USDT');
            $table->timestamp('settlement_time')->nullable()->comment('结算收回奖励日期');
            $table->decimal('settlement_rewards',20,8)->default(0)->comment('收回奖励金额 USDT');
            $table->tinyInteger('status')->default(1)->comment('状态 1 已发放奖励 2 已完全收回奖励 3 收回部分奖励 4 没有收回奖励');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_active_support');
    }
};
