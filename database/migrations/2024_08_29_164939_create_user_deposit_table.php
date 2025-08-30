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
        Schema::create('user_deposit', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('uid')->comment('用户UID');
            $table->bigInteger('coin_id')->comment('货币Id');
            $table->string('udun_logic_id')->nullable()->comment('U盾业务ID');
            $table->string('udun_block_id')->nullable()->comment('u盾返回的区块交易ID');
            $table->string('unique_callback')->nullable()->comment('u盾回调唯一ID');
            $table->string('wallet_address')->comment('收款钱包地址');
            $table->decimal('amount',20,8)->comment('充值金额');
            $table->decimal('usdt_value',20,8)->default(0)->comment('折合usdt金额');
            $table->decimal('real_amount',20,8)->default(0)->comment('实际到账额度');
            $table->string('fee')->default(0)->comment('手续费');
            $table->tinyInteger('status')->default(0)->comment('状态 0 申请中 1 到账 2 失败');
            $table->json('callback_raw')->nullable()->comment('回调详情');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_deposit');
    }
};
