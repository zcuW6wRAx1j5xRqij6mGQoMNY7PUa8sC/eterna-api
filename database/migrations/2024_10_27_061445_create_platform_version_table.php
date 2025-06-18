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
        Schema::create('platform_version', function (Blueprint $table) {
            $table->id();
            $table->string('version')->comment('版本号');
            $table->text('content')->nullable()->comment('更新内容');
            $table->char('platform')->comment('平台');
            $table->string('download_path')->comment('下载地址');
            $table->string('md5_sum')->comment('md5校验值');
            $table->tinyInteger('status')->comment('状态 0 未发布 1 发布');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_version');
    }
};
