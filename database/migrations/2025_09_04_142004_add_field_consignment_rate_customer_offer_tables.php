<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldConsignmentRateCustomerOfferTables extends Migration
{
    public function up()
    {
        Schema::table('customer_offers', function (Blueprint $table) {
            $table->integer('consignment_rate')->default(0)->nullable()
                ->comment('% đặt cọc đơn ký gửi');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_offers', function (Blueprint $table) {
            $table->dropColumn([
                'consignment_rate'
            ]);
        });
    }
}
