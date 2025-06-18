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
        Schema::create('platform_country', function (Blueprint $table) {
            $table->id();
            $table->string('flag')->nullable()->comment('旗帜');
            $table->string('name')->comment('名称');
            $table->string('phone_code')->comment('电话区号');
            $table->tinyInteger('status')->default(1)->comment('状态0关闭 1启用');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_country');
    }
};
