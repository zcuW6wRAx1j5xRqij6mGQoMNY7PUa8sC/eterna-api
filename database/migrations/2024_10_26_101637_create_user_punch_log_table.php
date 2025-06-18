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
        Schema::create('user_punch_log', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('uid')->comment('用户UID');
            $table->integer('rewards')->default(0)->comment('积分值');
            $table->date('punch_date')->nullable()->comment('签到日');
            $table->timestamps();

            $table->index(['uid', 'punch_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_punch_log');
    }
};
