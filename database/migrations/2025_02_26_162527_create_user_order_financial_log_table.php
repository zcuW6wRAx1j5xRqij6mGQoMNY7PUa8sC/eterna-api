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
        Schema::create('user_order_financial_log', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->comment('订单ID');
            $table->bigInteger('uid')->comment('用户ID');
            $table->date('settle_date')->comment('结算日期');
            $table->decimal('amount', 20, 4)->comment('当日结算金额');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_order_financial_log');
    }
};
