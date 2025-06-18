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
        Schema::create('user_order_pledge', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('uid')->comment('用户ID');
            $table->bigInteger('coin_id')->default(0)->comment('货币ID');
            $table->decimal('amount',20,8)->comment('质押币本金金额');
            $table->decimal('market_price',20,8)->default('0.0000')->comment('质押币实时价格');
            $table->decimal('quota',20,8)->comment('获得的USDC额度');
            $table->integer('duration')->comment('期限天数');
            $table->timestamp('start_at')->nullable()->comment('审核通过时间');
            $table->date('end_at')->nullable()->comment('结束时间');
            $table->timestamp('closed_at')->nullable()->comment('结单时间');
            $table->decimal('principal_remain',20,8)->default('0.0000')->comment('剩余赎回本金:amount');
            $table->decimal('redeem_remain',20,8)->default('0.0000')->comment('剩余回退usdc:quota');
            $table->char('status',50)->comment('状态:pending:待审 rejected:已拒绝 hold:质押中 closing:赎回中 closed:已结单');
            $table->bigInteger('operator')->default(0)->comment('审核人');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_order_pledge');
    }
};
