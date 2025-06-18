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
        Schema::create('admin_user_log', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_id')->comment('管理员ID');
            $table->char('log_type')->comment('日志类型');
            $table->json('content')->nullable()->comment('详细内容');
            $table->string('ip')->comment('操作IP');
            $table->string('device')->nullable()->comment('操作设备');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_user_log');
    }
};
