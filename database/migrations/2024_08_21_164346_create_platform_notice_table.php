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
        Schema::create('platform_notice', function (Blueprint $table) {
            $table->id();
            $table->text('subject')->comment('主题');
            $table->longText('content')->comment('内容');
            $table->tinyInteger('status')->default(1)->comment('是否展示 0 否 1是');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_notice');
    }
};
