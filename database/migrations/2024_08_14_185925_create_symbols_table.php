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
        Schema::create('symbols', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('交易对自定义名称');
            $table->string('symbol')->comment('交易对名称');
            $table->string('base_asset')->comment('交易货币');
            $table->string('quote_asset')->comment('计价货币');
            $table->bigInteger('coin_id')->default(0)->comment('货币ID');
            $table->string('binance_symbol')->comment('币安交易对别名');
            $table->integer('digits')->default(4)->comment('结算小数位');
            $table->tinyInteger('self_data')->default(0)->comment('自有行情 0 否 1 是');
            $table->tinyInteger('status')->default(1)->comment('状态 1开启 0关闭');

            $table->timestamps();
            $table->index('symbol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('symbols');
    }
};
