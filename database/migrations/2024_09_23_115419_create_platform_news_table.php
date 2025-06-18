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
        Schema::create('platform_news', function (Blueprint $table) {
            $table->id();
            $table->string('cover')->comment('封面图');
            $table->string('title')->comment('标题');
            $table->longText('content')->nullable()->comment('内容');
            $table->tinyInteger('status')->default(1)->comment('状态 0关闭 1开启');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_news');
    }
};
