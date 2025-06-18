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
        Schema::create('admin_menu', function (Blueprint $table) {
            $table->id();

            $table->integer('parent_id')->default(0)->comment('上级id');
            $table->char('show_name')->comment('菜单名');
            $table->char('url')->comment('菜单url');
            $table->tinyInteger('open_link')->default(0)->comment('是否外链');
            $table->char('icon')->nullable()->default('')->comment('icon');
            $table->tinyInteger('category')->default(1)->comment('类型 1菜单2按钮');
            $table->char('position')->default(99)->comment('菜单排序 越小越靠前');
            $table->tinyInteger('visible')->default(1)->comment('可见状态');
            $table->char('desc')->nullable()->default('')->comment('备注/描述信息');

            $table->timestamps();

            $table->comment('后台菜单表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_menu');
    }
};
