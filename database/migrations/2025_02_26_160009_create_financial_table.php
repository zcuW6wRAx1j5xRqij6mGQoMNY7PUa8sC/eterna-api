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
        Schema::create('financial', function (Blueprint $table) {
            $table->id();
            $table->char('category')->comment('类型 :flexible 活期  fixed 定期');
            $table->string('logo')->comment('logo');
            $table->string('name')->comment('名称');
            $table->json('duration')->comment('期限列表');
            $table->decimal('min_daily_rate', 20, 4)->comment('最小日利率');
            $table->decimal('max_daily_rate', 20, 4)->comment('最大日利率');
            $table->decimal('min_amount', 20, 4)->default(0)->comment('最小金额');
            $table->decimal('max_amount', 20, 4)->default(0)->comment('最小金额');
            $table->decimal('penalty_rate', 20, 4)->default(0)->comment('罚息 提前赎回违约金比例');
            $table->text('description')->nullable()->comment('描述');
            $table->integer('sort')->default(1)->comment('排序 数字小的在前');
            $table->tinyInteger('status')->default(1)->comment('状态 0禁用 1启用');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial');
    }
};
