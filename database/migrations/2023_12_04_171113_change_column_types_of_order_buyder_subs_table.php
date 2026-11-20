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
            $table->string('order_no')->nullable()
                ->comment('Mã đặt hàng, chính là order_buyders.order_no')
                ->change();
            $table->string('buyder_sub_id')->nullable()
                ->comment('Mã đặt hàng con (VOS)')->change();
            $table->string('taobao_purchase_id')->nullable()
                ->comment('Thanh toán Taobao')->change();
            $table->string('taobao_sub_purchase_id')->nullable()
                ->comment('Thanh toán Taobao')->change();
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
            $table->string('order_no')->comment('Mã đặt hàng, chính là order_buyders.order_no')->change();
            $table->string('buyder_sub_id')->comment('Mã đặt hàng con (VOS)')->change();
            $table->string('taobao_purchase_id')->comment('Thanh toán Taobao')->change();
            $table->string('taobao_sub_purchase_id')->comment('Thanh toán Taobao')->change();
        });
    }
};
