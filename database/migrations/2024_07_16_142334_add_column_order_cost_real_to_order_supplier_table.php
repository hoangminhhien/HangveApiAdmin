<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnOrderCostRealToOrderSupplierTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_supplier', function (Blueprint $table) {
            $table->decimal('order_cost_real', 20)->after('order_cost')->default(0)
                ->comment('Tổng giá trị đơn hàng thực tế, nhập trong TT thực tế NCC');
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
            $table->dropColumn('order_cost_real');
        });
    }
}
