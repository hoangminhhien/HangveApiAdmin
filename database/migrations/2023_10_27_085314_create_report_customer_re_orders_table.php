<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportCustomerReOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_customer_re_orders', function (Blueprint $table) {
            $table->id();
            $table->decimal('revenue', 20)->default(0)->comment('Doanh thu');
            $table->integer('total_customer')->comment('Tổng số lượng KH VIP')->default(0);
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
        Schema::dropIfExists('report_customer_re_orders');
    }
}
