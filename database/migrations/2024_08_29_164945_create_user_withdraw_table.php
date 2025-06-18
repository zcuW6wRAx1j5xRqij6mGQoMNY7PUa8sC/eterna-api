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
        Schema::create('user_withdraw', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->comment('订单号');
            $table->bigInteger('uid')->comment('用户UID');
            $table->bigInteger('wallet_id')->default(0)->comment('钱包ID');
            $table->string('coin_id')->comment('货币ID');
            $table->string('block')->nullable()->comment('链类型');
            $table->string('receive_wallet_address')->comment('收款钱包地址');
            $table->decimal('amount',20,8)->comment('申请金额');
            $table->decimal('real_amount',20,8)->default(0)->comment('实际到账金额');
            $table->string('fee')->nullable()->comment('手续费');
            $table->tinyInteger('audit_status')->default(0)->comment('审核状态 0 待审核 1通过 2驳回');
            $table->tinyInteger('status')->default(0)->comment('状态 0 申请中 1 到账 2 失败');
            $table->text('reason')->nullable()->comment('拒绝原因');
            $table->bigInteger('admin_id')->default(0)->comment('审核人员ID');
            $table->string('udun_logic_id')->nullable()->comment('U盾业务ID');
            $table->string('udun_block_id')->nullable()->comment('u盾返回的区块交易ID');
            $table->string('unique_callback')->nullable()->comment('u盾回调唯一ID');
            $table->json('callback_raw')->nullable()->comment('回调详情');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_withdraw');
    }
};
