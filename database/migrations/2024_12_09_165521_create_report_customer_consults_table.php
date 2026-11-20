<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportCustomerConsultsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_customer_consults', function (Blueprint $table) {
            $table->id();
            $table->integer('group_id')->index()->default(0)->nullable();
            $table->integer('customer_consulting_consignment')
                ->default(0)
                ->nullable()
                ->comment('KH tư vấn kí gửi => KH Kí gửi tạo tài khoản trong tuần báo cáo:
Nhãn: Kí gửi + Kí gửi đã lên đơn');
            $table->integer('total_consignment_customer_order')
                ->default(0)
                ->nullable()
                ->comment('Tổng KH kí gửi lên đơn => KH Kí gửi tạo tài khoản trong tuần báo cáo:
Nhãn: Kí gửi đã lên đơn');
            $table->integer('customer_consulting_order')
                ->default(0)
                ->nullable()
                ->comment('KH Tư vấn Order => KH Order tạo tài khoản trong tuần báo cáo:
Gồm các Nhãn: Tham khảo chưa có nhu cầu ngay, Tham Khảo, Đang Follow, Đã lên đơn');
            $table->integer('total_customer_order')
                ->default(0)
                ->nullable()
                ->comment('Tổng KH mới lên đơn => KH Order tạo tài khoản trong tuần báo cáo:
Nhãn: Đã lên đơn');
            $table->integer('new_customer_order')
                ->default(0)
                ->nullable()
                ->comment('KH mới đặt hàng');
            $table->decimal('new_order_value', 20)
                ->default(0)
                ->nullable()
                ->comment('Giá trị đơn hàng mới');
            $table->decimal('service_fee', 20)
                ->default(0)
                ->nullable()
                ->comment('Phí Dịch Vụ');
            $table->integer('total_qty_new_customer_order')
                ->default(0)
                ->nullable()
                ->comment('Tổng Số Lượng Đơn KH Mới');
            $table->decimal('total_value_new_customer_order', 20)
                ->default(0)
                ->nullable()
                ->comment('Tổng Giá Trị Đơn KH Mới');
            $table->decimal('total_service_fee_new_customer', 20)
                ->default(0)
                ->nullable()
                ->comment('Tổng Phí Dịch Vụ KH Mới');
            $table->string('type', 10)->index()->nullable();
            $table->tinyInteger('week')->index()->nullable();
            $table->tinyInteger('month')->index()->nullable();
            $table->string('year', 10)->index()->nullable();
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
        Schema::dropIfExists('report_customer_consults');
    }
}
