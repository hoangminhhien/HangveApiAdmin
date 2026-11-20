<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCostRealToOrderRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->decimal('order_cost_real', 8, 2)->nullable()->after('china_shipping_cost_refund');
            $table->decimal('china_shipping_cost_real', 8, 2)->nullable()->after('order_cost_real');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->dropColumn('order_cost_real');
            $table->dropColumn('china_shipping_cost_real');
        });
    }
}
