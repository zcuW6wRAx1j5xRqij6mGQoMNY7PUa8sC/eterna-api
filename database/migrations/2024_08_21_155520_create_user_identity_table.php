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
        Schema::create('user_identity', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('uid')->comment('用户UID');
            $table->bigInteger('country_id')->comment('国家ID');
            $table->string('first_name')->comment('名字');
            $table->string('last_name')->comment('姓');
            $table->string('document_number')->comment('证件号');
            $table->string('document_type')->comment('证件类型');
            $table->string('document_frontend')->comment('证件正面');
            $table->string('face')->nullable()->comment('人脸自拍照片');
            $table->tinyInteger('process_status')->default(0)->comment('状态 : 0 待审核 1通过 2驳回');
            $table->text('reason')->nullable()->comment('原因');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_realname');
    }
};
