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
        Schema::create('ieo_symbols', function (Blueprint $table) {
            $table->id();
            $table->string('ieo_name')->comment('IEO名称');
            $table->bigInteger('symbol_id')->comment('交易对ID');
            $table->bigInteger('coin_id')->comment('货币ID');
            $table->string('pdf')->nullable()->comment('白皮书');
            $table->decimal('total_supply',30,8)->comment('发行量');
            $table->decimal('unit_price',30,8)->comment('发行单价');
            $table->decimal('forecast_earnings',30,2)->default(0)->comment('预期收益');
            $table->decimal('min_order_price',30,8)->comment('单笔最小购买金额');
            $table->decimal('max_order_price',30,8)->comment('单笔最大购买金额');
            $table->decimal('processing',10,2)->default(0)->comment('进度');
            $table->timestamp('order_start_time')->nullable()->comment('认购开始时间');
            $table->timestamp('order_end_time')->nullable()->comment('认购结束时间');
            $table->timestamp('public_time')->nullable()->comment('上市时间');
            $table->timestamp('release_time')->nullable()->comment('公布结果时间');

            $table->tinyInteger('status')->default(0)->comment('状态 : 0 未开始 1 认购中 2 抽签中 3 已结束');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ieo_symbols');
    }
};
