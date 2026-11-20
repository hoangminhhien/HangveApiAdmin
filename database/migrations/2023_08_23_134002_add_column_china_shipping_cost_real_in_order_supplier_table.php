<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnChinaShippingCostRealInOrderSupplierTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_supplier', function (Blueprint $table) {
            $table->decimal('china_shipping_cost_real', 20)->after('china_shipping_cost')->default(0)->comment('Tổng phí ship TQ thực tế, nhập trong TT thực tế NCC');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_supplier', function (Blueprint $table) {
            $table->dropColumn('china_shipping_cost_real');
        });
    }
}
