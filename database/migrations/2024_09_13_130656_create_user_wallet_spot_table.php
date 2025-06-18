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
        Schema::create('user_wallet_spot', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('uid')->comment('用户UID');
            $table->bigInteger('coin_id')->comment('币ID');
            $table->decimal('amount',20,8)->default(0)->comment('数量');
            $table->decimal('lock_amount',20,8)->default(0)->comment('锁定数量');
            $table->decimal('usdt_value',20,8)->default(0)->comment('usdt价格');

            $table->index(['uid', 'coin_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_wallet_spot');
    }
};
