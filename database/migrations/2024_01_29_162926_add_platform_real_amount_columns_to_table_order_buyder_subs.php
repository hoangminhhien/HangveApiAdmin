<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_buyder_subs', function (Blueprint $table) {
            $table->unsignedDecimal('platform_total_amount', 10, 2)
                ->default(0)
                ->after('logistic_number')
                ->comment('Tổng tiền thanh toán thực tế do sàn trả về, tính bằng tệ,
                    bằng tổng tiền hàng thực tế + tiền ship nội địa thực tế');
            $table->unsignedDecimal('platform_goods_amount', 10, 2)
                ->default(0)
                ->after('platform_total_amount')
                ->comment('Tổng tiền hàng thanh toán thực tế do sản trả về, tính bằng tệ');
            $table->unsignedDecimal('platform_domestic_post_fee', 6, 2)
                ->default(0)
                ->after('platform_goods_amount')
                ->comment('Tiền ship nội địa thực tế do sàn trả về, tính bằng tệ');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_buyder_subs', function (Blueprint $table) {
            $table->dropColumn([
                'platform_total_amount',
                'platform_goods_amount',
                'platform_domestic_post_fee',
            ]);
        });
    }
};
