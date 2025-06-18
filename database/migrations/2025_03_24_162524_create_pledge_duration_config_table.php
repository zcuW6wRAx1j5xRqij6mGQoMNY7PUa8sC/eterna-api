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
        Schema::create('pledge_coin_config', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('coin_id')->default(0)->comment('货币ID');
            $table->tinyInteger('status')->default(7)->comment('状态 1:正常 0:下架');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pledge_coin_config');
    }
};
