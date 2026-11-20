<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tổng hợp báo cáo theo ngày từ bảng Orders và Consignments
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_order_consignment_date', function (Blueprint $table) {
            $table->id();
            $table->integer('staff_group_id')->nullable()->comment('Nhân viên kinh doanh');
            $table->integer('count_order')->default(0)->comment('Số lượng order');
            $table->decimal('order_cost', 20)->default(0)->comment('Giá trị đơn hàng');
            $table->decimal('order_fee', 20)->default(0)->comment('Phí dịch vụ');
            $table->decimal('international_shipping_cost', 20)->default(0)->comment('Phí vận chuyển QT');
            $table->decimal('woodworking_cost', 20)->default(0)->comment('Phí đóng gỗ');
            $table->decimal('shock_proof_cost', 20)->default(0)->comment('Phí chống sốc');
            $table->decimal('storage_cost', 20)->default(0)->comment('Phí lưu kho');
            $table->decimal('insurance_cost', 20)->default(0)->comment('Phí bảo hiểm');
            $table->decimal('inspection_cost', 20)->default(0)->comment('Phí kiểm đếm');
            $table->decimal('china_shipping_cost', 20)->default(0)->comment('Phí vận chuyển TQ từ NCC -> kho');
            $table->date('date')->nullable()->comment('Báo cáo theo ngày');

            // Phần kế toán thêm 2 key này
            $table->decimal('order_cost_old', 20)
                ->default(0)
                ->nullable()
                ->comment('Tổng giá trị đơn hàng lúc báo giá');
            $table->decimal('china_shipping_cost_old', 20)
                ->default(0)->nullable()
                ->comment('Phí vận chuyển TQ từ NCC -> kho lúc báo giá');

            $table->timestamps();
            $table->index("date", "date_index");
            $table->index(["date", "staff_group_id"], "date_staff_index");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('report_order_consignment_date');
    }
};