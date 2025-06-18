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
        Schema::create('platform_announcement', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('公告标题');
            $table->text('content')->comment('公告内容');
            $table->tinyInteger('status')->default(0)->comment('状态 : 0 未发布 1 已发布');
            $table->tinyInteger('user_read_times')->default(0)->comment('弹出次数 0 一直弹出 1 用户已读不在弹出');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_announcement');
    }
};
