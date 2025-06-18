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
        Schema::create('admin_role', function (Blueprint $table) {
            $table->id();

            $table->char('show_name')->comment('角色名称');
            $table->char('desc')->default('')->comment('备注/描述信息');
            $table->tinyInteger('status')->default(1)->comment('状态 0关闭 1开启');

            $table->timestamps();
            $table->comment('后台角色表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_role');
    }
};
