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
        Schema::create('user_wallet_pledge_flow', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('uid')->comment('用户ID');
            $table->bigInteger('coin_id')->default(0)->comment('货币ID');
            $table->char('flow_type',50)->comment('状态:pending:待审 rejected:已拒绝 hold:质押中 closing:赎回中 closed:已结单');
            $table->decimal('before_amount',20,8)->default(0)->comment('操作前金额');
            $table->decimal('amount',20,8)->default(0)->comment('金额');
            $table->decimal('after_amount',20,8)->default(0)->comment('操作前金额');
            $table->bigInteger('relation_id')->default(0)->comment('关联ID');
            $table->json('extra')->nullable()->comment('额外项');
            $table->string('remark')->nullable()->comment('备注');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_wallet_pledge_flow');
    }
};
