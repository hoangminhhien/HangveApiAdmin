<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportPerformanceStaffSaleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_performance_staff_sale', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->comment('id nhân viên');
            $table->string('user_name')->comment('tên nhân viên');
            $table->integer('time_sale_fastest')->comment('thời gian chốt đơn nhanh nhất (miliseconds)');
            $table->integer('time_sale_slowest')->comment('thời gian chốt đơn chậm nhất (miliseconds)');
            $table->integer('time_sale_average')->comment('thời gian chốt đơn trung bình (miliseconds)');
            $table->integer('total_orders')->comment('tổng đơn chốt');
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
        Schema::dropIfExists('report_performance_staff_sale');
    }
}
