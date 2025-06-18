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
        Schema::create('ieo_orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('ieo_id')->comment('IEO数据ID');
            $table->bigInteger('uid')->comment('用户UID');
            $table->decimal('unit_price',30,8)->comment('申请认购单价');
            $table->decimal('quantity',30,8)->comment('申请认购数量');
            $table->decimal('total_amount',30,8)->comment('申请认购金额 USDT');

            $table->decimal('result_unit_price',30,8)->default(0)->comment('中签单价');
            $table->decimal('result_total_amount',30,8)->default(0)->comment('中签金额');
            $table->decimal('result_quantity',30,8)->default(0)->comment('中签货币数量');
            $table->timestamp('result_time')->nullable()->comment('中签日期');
            $table->decimal('subscribed_amount',30,8)->default(0)->comment('已认缴金额');
            $table->decimal('locked_amount',30,8)->default(0)->comment('已锁定金额');

            $table->tinyInteger('status')->default(0)->comment('状态 : 0 已申请 1 进行中 2 已完成 3 已取消');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ieo_orders');
    }
};
