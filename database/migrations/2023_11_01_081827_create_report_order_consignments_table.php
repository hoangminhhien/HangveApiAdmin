<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportOrderConsignmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_order_consignments', function (Blueprint $table) {
            $table->id();
            $table->decimal('fee_ship', 2)->default(0)->comment('Phí vận chuyển');
            $table->integer('total_customer')->comment('KH có đơn hàng đc gán nv chăm sóc')->default(0);
            $table->integer('total_customer_order')->comment('Số lượng KH lên đơn hàng trong tuần')->default(0);
            $table->integer('week')->comment('Tuần trong năm')->nullable()->index();
            $table->string('year')->comment('Năm')->nullable()->index();
            $table->double('total_weight', 2)->comment('Tổng cân nặng (KG)')->default(0);
            $table->double('total_volume', 2)->comment('Tổng thể tích (M3)')->default(0);
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
        Schema::dropIfExists('report_order_consignments');
    }
}
