<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateColumnToOrderPerformanceHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_performance_histories', function (Blueprint $table) {
            $table->dropColumn([
                'order_id', 'customer_id', 'time_sale',
                'total_orders_day', 'total_orders_week',
                'total_orders_month', 'total_orders_year'
            ]);
            $table->integer('total_order')->default(0)->after('year')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_performance_histories', function (Blueprint $table) {
            $table->dropColumn([
                'total_order'
            ]);
            $table->string('order_id')->comment('id đơn hàng (chỉ tính đơn đầu của khách)');
            $table->string('customer_id')->comment('id khách hàng');
            $table->integer('time_sale')->comment('thời gian chốt đơn (miliseconds)');
            $table->integer('total_orders_day')->comment('tổng đơn trong ngày');
            $table->integer('total_orders_week')->comment('tổng đơn trong tuần');
            $table->integer('total_orders_month')->comment('tổng đơn trong tháng');
            $table->integer('total_orders_year')->comment('tổng đơn trong năm');
        });
    }
}
