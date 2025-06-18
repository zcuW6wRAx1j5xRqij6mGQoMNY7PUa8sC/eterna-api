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
        Schema::create('platform_announcement_read_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('announcement_id')->comment('公告ID');
            $table->bigInteger('user_id')->comment('用户ID');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_announcement_read_logs');
    }
};
