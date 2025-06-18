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
        Schema::create('user_collection_symbols', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('uid')->comment('用户UID');
            $table->char('symbol_type',10)->comment('交易对类型 : futures 合约 spot 现货');
            $table->bigInteger('symbol_id')->comment('交易对ID');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_collection_symbols');
    }
};
