<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportCustomerVipOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_customer_vip_orders', function (Blueprint $table) {
            $table->id();
            $table->decimal('revenue', 20)->default(0)->comment('Doanh thu');
            $table->integer('total_customer')->comment('Tổng số lượng KH VIP')->default(0);
            $table->integer('total_customer_has_order')->comment('Tổng số lượng KH VIP có đơn')->default(0);
            $table->integer('week')->comment('Tuần trong năm')->nullable()->index();
            $table->string('year')->comment('Năm')->nullable()->index();
            $table->integer('total_order_all')->comment('Tổng số lượng đơn')->default(0);
            $table->integer('total_order_order')->comment('Tổng số lượng đơn ở trạng thái đặt hàng')->default(0);
            $table->tinyInteger('level')->comment('Cấp độ')->nullable()->index();
            $table->string('name')->comment('Tên cấp độ')->nullable();
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
        Schema::dropIfExists('report_customer_vip_orders');
    }
}
