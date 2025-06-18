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
        Schema::create('platform_protocol', function (Blueprint $table) {
            $table->id();
            $table->char('proto_type')->comment('类型 : about_me 关于我们 terms_and_conditions 用户协议 privacy_policy 隐私协议');
            $table->longText('content')->comment('内容');
            $table->char('language')->default('zh-CN')->comment('语言');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_protocol');
    }
};
