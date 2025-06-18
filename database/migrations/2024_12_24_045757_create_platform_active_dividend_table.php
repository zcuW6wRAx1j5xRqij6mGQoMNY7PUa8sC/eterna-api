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
        Schema::create('platform_active_dividend', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('uid');
            $table->decimal('exchange_balance',30,8)->comment('用于兑换的余额');
            $table->decimal('exchange_amount', 30, 8)->default(0)->comment('兑换金额');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_active_dividend');
    }
};
