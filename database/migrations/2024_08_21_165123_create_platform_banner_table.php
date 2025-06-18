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
        Schema::create('platform_banner', function (Blueprint $table) {
            $table->id();
            $table->char('platform')->comment('平台 : app -> APP web -> WEB');
            $table->string('img_path')->comment('图片地址');
            $table->string('link_url')->nullable()->comment('点击跳转');
            $table->integer('sort')->default(1)->comment('排序 数字小在前');
            $table->tinyInteger('status')->default(1)->comment('是否展示 0 否 1是');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_banner');
    }
};
