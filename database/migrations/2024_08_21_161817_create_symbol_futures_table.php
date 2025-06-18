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
        Schema::create('symbol_futures', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('symbol_id')->comment('交易对ID');
            $table->bigInteger('coin_id')->comment('货币ID');

            $table->decimal('buy_spread',20,8)->default(0)->comment('买入点差');
            $table->decimal('sell_spread',20,8)->default(0)->comment('卖出点差');
            $table->decimal('fee',20,8)->default(0)->comment('手续费');

            $table->integer('sort')->default(1)->comment('排序显示 数字从小到大');
            $table->tinyInteger('status')->default(1)->comment('交易状态 : 0 关闭 1 开启');

            $table->index('symbol_id');
            $table->index('coin_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('derivatives_symbols');
    }
};
