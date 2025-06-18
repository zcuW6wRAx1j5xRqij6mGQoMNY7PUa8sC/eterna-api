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
        Schema::create('user_wallet_futures', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('uid')->comment('用户UID');
            $table->decimal('balance',20,8)->default(0)->comment('余额');
            $table->decimal('lock_balance',20,8)->default(0)->comment('锁定金额');
            $table->decimal('floating_profit',20,8)->default(0)->comment('浮动盈利/亏损');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_wallet_futures');
    }
};
