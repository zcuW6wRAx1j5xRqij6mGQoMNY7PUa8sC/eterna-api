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
        Schema::create('user_order_spot', function (Blueprint $table) {
            $table->id();

            $table->string('order_code')->comment('订单编号');
            $table->bigInteger('uid')->comment('用户UID');
            $table->bigInteger('spot_id')->comment('现货ID');
            $table->bigInteger('symbol_id')->comment('交易对ID');

            $table->char('side',5)->comment('交易方向 : sell / buy');
            $table->char('trade_type',20)->comment('交易类型 : limit / market');
            $table->decimal('price',20,8)->default(0)->comment('委托报价');
            $table->decimal('market_price',20,8)->default(0)->comment('市场价格');
            $table->decimal('match_price',20,8)->default(0)->comment('成交价格');
            $table->timestamp('match_time')->nullable()->comment('成交时间');
            $table->decimal('spread',20,8)->default(0)->comment('点差');
            $table->decimal('fee',20,8)->comment('手续费');
            $table->char('base_asset',10)->comment('交易货币');
            $table->char('quote_asset',10)->comment('计价货币');

            $table->decimal('volume',20,8)->comment('交易量');
            $table->decimal('trade_volume',20,8)->comment('交易额');

            $table->char('trade_status',50)->comment('持仓状态 : pending 挂单中 success 成交 failed 失败');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_spot_order');
    }
};
