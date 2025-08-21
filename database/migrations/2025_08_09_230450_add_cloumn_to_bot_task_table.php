<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bot_task', function (Blueprint $table) {
            $table->dropColumn('close_offset');
            $table->dropColumn('target_max');
            $table->dropColumn('target_min');
            $table->dropColumn('rate_max');
            $table->dropColumn('rate_min');
            $table->decimal('open', 20, 8)->unsigned()->comment('开盘价')->after('symbol_type');
            $table->decimal('high', 20, 8)->unsigned()->comment('最高价')->after('open');
            $table->decimal('low', 20, 8)->unsigned()->comment('最低价')->after('high');
            $table->decimal('sigma', 6, 4)->unsigned()->comment('收盘价')->after('close');
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_task', function (Blueprint $table) {
            $table->decimal('close_offset', 20, 8)->comment('范围下限')->after('close');
            $table->decimal('target_max', 20, 8)->comment('最高价')->after('close_offset');
            $table->decimal('target_min', 20, 8)->comment('最低价')->after('target_max');
            $table->decimal('rate_max', 20, 8)->comment('最大涨幅')->after('target_min');
            $table->decimal('rate_min', 20, 8)->comment('最大跌幅')->after('rate_max');
            $table->dropColumn('high');
            $table->dropColumn('low');
            $table->dropColumn('open');
            $table->dropColumn('sigma');
        });
    }
};
