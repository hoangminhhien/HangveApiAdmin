<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderPerformanceHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_performance_histories', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->comment('id đơn hàng (chỉ tính đơn đầu của khách)');
            $table->string('customer_id')->comment('id khách hàng');
            $table->string('staff_id')->comment('id nhân viên');
            $table->date('date')->comment('ngày chốt đơn');
            $table->integer('week')->comment('tuần chốt đơn');
            $table->integer('month')->comment('tháng chốt đơn');
            $table->integer('year')->comment('năm chốt đơn');
            $table->integer('time_sale')->comment('thời gian chốt đơn (miliseconds)');
            $table->integer('total_orders_day')->comment('tổng đơn trong ngày');
            $table->integer('total_orders_week')->comment('tổng đơn trong tuần');
            $table->integer('total_orders_month')->comment('tổng đơn trong tháng');
            $table->integer('total_orders_year')->comment('tổng đơn trong năm');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_performance_histories');
    }
}
