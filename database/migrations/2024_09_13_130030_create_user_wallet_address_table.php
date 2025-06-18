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
        Schema::create('user_wallet_address', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('uid');
            $table->bigInteger('platform_wallet_id')->comment('平台钱包ID');
            $table->string('address')->comment('钱包地址');
            $table->decimal('total_withdraw',20,8)->default(0)->comment('总计入金数量');
            $table->decimal('total_withdraw_usdt',20,8)->default(0)->comment('总计入金 USDT价值');
            $table->decimal('total_deposit',20,8)->default(0)->comment('总计出金数量');
            $table->decimal('total_deposit_usdt',20,8)->default(0)->comment('总计出金 USDT价值');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_wallet_address');
    }
};
