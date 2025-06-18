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
        Schema::create('user_order_financial', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('financial_id')->comment('理财产品ID');
            $table->bigInteger('uid')->comment('用户ID');
            $table->integer('duration')->comment('期限');
            $table->decimal('amount', 20, 4)->comment('本金金额');
            $table->decimal('daily_rate', 20, 4)->comment('日利率');
            $table->decimal('total_rate', 20, 4)->comment('总利率');
            $table->decimal('expected_total_profit', 20, 4)->comment('预计总收益');
            $table->decimal('settled_total_profit', 20, 4)->default(0)->comment('已结算总收益');
            $table->timestamp('start_at')->nullable()->comment('开始时间');
            $table->timestamp('end_at')->nullable()->comment('结束时间');
            $table->timestamp('settled_at')->nullable()->comment('结算时间');
            $table->decimal('penalty_amount')->default(0)->comment('赎回罚金');
            $table->char('status')->comment('状态:pending 待结算 settled 已结算');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_order_financial');
    }
};
