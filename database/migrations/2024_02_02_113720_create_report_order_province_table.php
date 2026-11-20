<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportOrderProvinceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_order_province', function (Blueprint $table) {
            $table->id();
            $table->string('province_id');
            $table->integer('total_customer')->default(0)->comment('Tổng khách hàng lên đơn');
            $table->integer('total_customer_deposit')->default(0)->comment('Tổng khách hàng đã đặt cọc');
            $table->integer('total_order')->default(0)->comment('Tổng đơn hàng');
            $table->integer('total_order_created')->default(0)->comment('Tổng đơn mới tạo và đơn hủy');
            $table->integer('total_order_deposit')->default(0)->comment('Tổng đơn khách đã đặt cọc');
            $table->double('total_amount')->default(0)->comment('Tổng giá trị đơn hàng');
            $table->double('total_fee')->default(0)->comment('Tổng phí dịch vụ');
            $table->double('total_transport')->default(0)->comment('Tổng phí vận chuyển');
            $table->double('conversion_rate_customer')->default(0)->comment('Tỉ lệ chuyển đổi theo khách hàng');
            $table->double('conversion_rate_order')->default(0)->comment('Tỉ lệ chuyển đổi theo đơn hàng');
            $table->date("date_report")->comment("Tổng hợp báo cáo theo ngày");
            $table->string("type")->comment("Loại thời gian báo cáo: day, week, month, year");
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
        Schema::dropIfExists('report_order_province');
    }
}
