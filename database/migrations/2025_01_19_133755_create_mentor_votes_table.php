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
        Schema::create('mentor_votes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('mentor_id')->comment('导师ID');
            $table->bigInteger('user_id')->comment('用户ID');
            $table->date('vote_date')->comment('投票日期');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentor_votes');
    }
};
