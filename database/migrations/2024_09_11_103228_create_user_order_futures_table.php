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
        Schema::create('user_order_futures', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->comment('订单编号');
            $table->bigInteger('uid')->comment('用户UID');

            $table->char('margin_type')->comment('保证金类型 : isolated 逐仓 crossed 全仓');

            $table->decimal('margin',20,8)->default(0)->comment('保证金');
            $table->decimal('margin_ratio',20,2)->default(0)->comment('保证金比例');

            $table->decimal('volume',20,8)->default(0)->comment('合约量');
            $table->decimal('trade_volume',20,8)->default(0)->comment('交易额');
            $table->decimal('lots',20,1)->default(0)->comment('手数');

            $table->bigInteger('futures_id')->comment('合约交易对ID');
            $table->bigInteger('symbol_id')->comment('总交易对ID');
            $table->char('side',5)->comment('交易方向 : sell / buy');
            $table->char('trade_type',20)->comment('交易类型 : limit / market');
            $table->integer('leverage')->default(0)->comment('杠杆倍数');

            $table->decimal('price',20,8)->default(0)->comment('开仓委托价格');
            $table->decimal('match_price',20,8)->default(0)->comment('开仓成交价格');
            $table->decimal('market_price',20,8)->default(0)->comment('市场价格');
            $table->timestamp('match_time')->nullable()->comment('成交时间');

            $table->decimal('profit',20,8)->default(0)->comment('盈亏');
            $table->decimal('profit_ratio',20,2)->default(0)->comment('浮动盈亏比例');
            $table->decimal('sl',20,8)->default(0)->comment('SL设定值');
            $table->decimal('tp',20,8)->default(0)->comment('TP设定值');

            $table->decimal('open_price',20,8)->default(0)->comment('开仓价格');
            $table->decimal('open_spread',20,8)->default(0)->comment('开仓点差');
            $table->decimal('open_fee',20,8)->default(0)->comment('开仓手续费');

            $table->decimal('close_price',20,8)->default(0)->comment('平仓价格');
            $table->timestamp('close_time')->nullable()->comment('平仓时间');
            $table->decimal('close_spread',20,8)->default(0)->comment('平仓点差');
            $table->decimal('close_fee',20,8)->default(0)->comment('平仓手续费');

            $table->decimal('force_close_price',20,8)->default(0)->comment('强平价格');
            $table->char('close_type',50)->nullable()->comment('平仓类型');
            $table->char('trade_status',50)->comment('持仓状态 : pending 等待 open 持仓中 closeing 平仓中 closed 已平仓');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_order_futures');
    }
};
