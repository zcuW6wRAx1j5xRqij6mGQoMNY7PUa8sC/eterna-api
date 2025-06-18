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
        Schema::create('platform_wallet', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('coin_id')->comment('货币ID');
            $table->string('name')->comment('货币名称');
            $table->string('block')->comment('链名称');

            $table->string('udun_name')->nullable()->comment('u盾名称');
            $table->string('udun_coin_type')->nullable()->comment('u盾 coin_type');
            $table->string('udun_main_coin_type')->nullable()->comment('u盾 Main coin Type');

            $table->string('binance_symbol')->nullable()->comment('币安市场交易对-用于市场价格查询');

            $table->tinyInteger('spot_withdraw')->default(1)->comment('可现货出金 : 0 否 1 是');
            $table->tinyInteger('spot_deposit')->default(1)->comment('可现货入金 : 0 否 1 是');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_wallet');
    }
};
