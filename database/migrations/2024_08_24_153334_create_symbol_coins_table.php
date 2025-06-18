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
        Schema::create('symbol_coins', function (Blueprint $table) {
            $table->id();
            $table->string('logo')->nullable()->comment('logo显示');
            $table->string('name')->comment('货币名称');
            $table->string('block')->comment('链名称');
            $table->string('full_name')->nullable()->comment('全称');
            $table->integer('sort')->default(1)->comment('排序 数字越小在前');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_coin');
    }
};
