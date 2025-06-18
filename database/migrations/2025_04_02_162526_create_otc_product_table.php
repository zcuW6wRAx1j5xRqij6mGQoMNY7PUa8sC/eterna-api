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
        Schema::create('otc_product', function (Blueprint $table) {
            $table->id();

            $table->char('title',30)->comment('产品名称');
            $table->integer('duration')->unsigned()->default(1)->comment('期限分钟数');
            $table->tinyInteger('rating')->unsigned()->default(5)->comment('星级评分（0-5）');
            $table->integer('total_count')->unsigned()->default(0)->comment('成单数量');
            $table->decimal('success_rate',5,2)->unsigned()->default(0.00)->comment('成单率（单位：%）');
            $table->decimal('total_amount',18,3)->unsigned()->default(0.00)->comment('成交总额');
            $table->integer('coin_id')->unsigned()->default(25)->comment('货币类型默认usdc:25');
            $table->decimal('min_limit',18)->unsigned()->default(0.00)->comment('最低限额');
            $table->decimal('max_limit',18)->unsigned()->default(0.00)->comment('最高限额');
            $table->decimal('buy_price',11,3)->unsigned()->default(1.001)->comment('买入单价');
            $table->decimal('sell_price',11,3)->unsigned()->default(1.00)->comment('卖出单价');
            $table->tinyInteger('status')->unsigned()->default(1)->comment('状态:1正常，2下架');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otc_product');
    }
};
