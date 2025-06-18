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
        Schema::create('mentors', function (Blueprint $table) {
            $table->id();
            $table->string('avatar')->comment('头像');
            $table->string('name')->comment('名称');
            $table->text('description')->comment('描述');
            $table->integer('votes')->comment('投票数');
            $table->decimal('process', 5, 2)->comment('进度');
            $table->tinyInteger('status')->default(1)->comment('状态 0 关闭 1开启');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentors');
    }
};
