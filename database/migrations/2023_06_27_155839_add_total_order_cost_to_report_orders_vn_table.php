<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTotalOrderCostToReportOrdersVnTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_orders_vn', function (Blueprint $table) {
            $table->decimal('total_order_cost', 20)->after('order_cost')->default(0)->comment('Tổng tiền hàng');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_orders_vn', function (Blueprint $table) {
            $table->dropColumn([
                'total_order_cost',
            ]);
        });
    }
}
