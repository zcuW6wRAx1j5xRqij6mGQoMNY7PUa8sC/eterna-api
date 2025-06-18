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
        Schema::create('otc_order', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('uid')->comment('用户ID');
            $table->bigInteger('product_id')->unsigned()->default(1)->comment('产品ID');
            $table->string('trade_type',10)->default('buy')->comment('交易类型sell/buy');
            $table->decimal('quantity',20, 8)->comment('买入数量，用户手输的');
            $table->decimal('amount',18,2)->comment('买入总额');
            $table->string('payment_method',45)->default('')->comment('支付方式');
            $table->string('comments',200)->default('')->comment('备注信息');
            $table->decimal('sell_price',11,3)->unsigned()->default('0.00')->comment('卖出单价');
            $table->decimal('buy_price',11,3)->unsigned()->default('0.00')->comment('买入单价');
            $table->integer('buy_auditor')->nullable()->comment('买入审核人ID');
            $table->timestamp('buy_audit_at')->nullable()->comment('买入审核时间');
            $table->integer('sell_auditor')->nullable()->comment('卖出审核人ID');
            $table->timestamp('sell_audit_at')->nullable()->comment('卖出审核时间');
            $table->char('status',20)->default('pending')->comment('状态:pending:待审 rejected:已拒绝 hold:持仓中 closing:卖出审核中 closed:已结单');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otc_order');
    }
};
