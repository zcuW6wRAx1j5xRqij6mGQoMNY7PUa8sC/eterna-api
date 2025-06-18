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
        Schema::create('admin_relation', function (Blueprint $table) {
            $table->id();
            $table->char('role_id')->comment('角色id');
            $table->char('menu_id')->comment('菜单id');
            $table->timestamps();
            $table->index(['role_id']);
            $table->comment('后台角色菜单关系表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_relation');
    }
};
