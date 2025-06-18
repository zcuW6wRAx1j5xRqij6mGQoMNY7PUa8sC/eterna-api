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
        Schema::create('platform_symbol_price', function (Blueprint $table) {
            $table->id();
            $table->char('symbol_type')->comment('symbol类型 spot 现货 futures 合约');
            $table->bigInteger('symbol_id')->comment('symbol ID');
            $table->bigInteger('spot_id')->default(0)->comment('现货ID');
            $table->bigInteger('futures_id')->default(0)->comment('合约ID');
            $table->timestamp('start_time')->nullable()->comment('开始时间');
            $table->bigInteger('duration_time')->default(0)->comment('持续时间 : 分钟');
            $table->decimal('fake_price',20,8)->default(0)->comment('控盘价格');
            $table->string('task_id')->nullable()->comment('任务ID');
            $table->tinyInteger('status')->default(0)->comment('状态 1进行中 0 未进行');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_symbol_price');
    }
};
