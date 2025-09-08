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
        Schema::table('otc_product', function (Blueprint $table) {
            $table->decimal('sell_min_limit', 18)->comment('最低出售金额')->default(0)->unsigned()->after('max_limit');
            $table->decimal('sell_max_limit', 18)->comment('最高出售金额')->default(0)->unsigned()->after('sell_min_limit');
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('otc_product', function (Blueprint $table) {
            $table->dropColumn('sell_min_limit');
            $table->dropColumn('sell_max_limit');
        });
    }
};
