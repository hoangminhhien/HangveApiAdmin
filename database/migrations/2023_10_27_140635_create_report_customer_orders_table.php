<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportCustomerOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_customer_orders', function (Blueprint $table) {
            $table->id();
            $table->decimal('revenue', 20)->default(0)->comment('Tổng giá trị tiền hàng KH đặt');
            $table->decimal('service_charge', 20)->default(0)->comment('Phí đặt hàng');
            $table->integer('total_customer')->comment('KH có đơn hàng đc gán nv chăm sóc')->default(0);
            $table->integer('total_customer_order')->comment('Số lượng KH lên đơn hàng trong tuần')->default(0);
            $table->integer('week')->comment('Tuần trong năm')->nullable()->index();
            $table->string('year')->comment('Năm')->nullable()->index();
            $table->integer('total_order')->comment('Tổng số lượng đơn')->default(0);
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
        Schema::dropIfExists('report_customer_orders');
    }
}
