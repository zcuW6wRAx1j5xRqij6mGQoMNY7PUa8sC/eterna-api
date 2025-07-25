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
        Schema::create('bot_task', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('symbol_id')->comment('交易对ID');
            $table->char('symbol_type')->comment('交易对类型: futures 合约, spot 现货');
            $table->decimal('high',20,8)->unsigned()->comment('最高价');
            $table->decimal('low',20,8)->unsigned()->comment('最低价');
            $table->decimal('close',20,8)->unsigned()->comment('目标价');
            $table->tinyInteger('status')->comment('任务状态 1:正常,0:取消');
            $table->timestamp('start_at')->nullable()->comment('开始时间');
            $table->timestamp('end_at')->nullable()->comment('结束时间');
            $table->bigInteger('creator')->nullable()->comment('任务创建者');
            $table->bigInteger('updater')->nullable()->comment('任务修改者');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_task');
    }
};
