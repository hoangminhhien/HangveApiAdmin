<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderDetailChangeToRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('order_status')->after('order_code')->nullable();
            $table->double('order_percent')->after('order_status')->default(0)->nullable(); // Phần trăm phí đặt hàng
            $table->double('total_money_item')->after('amount_cn')->default(0)->unsigned()->nullable(); // Tổng tiền hàng hoàn
            $table->double('total_fee_service')->after('total_money_item')->default(0)->unsigned()->nullable(); // Tổng tiền phí hoàn
            $table->double('total_fee_inspection')->after('total_fee_service')->default(0)->unsigned()->nullable(); // Tổng tiền phí kiểm đếm
            $table->double('china_shipping_cost')->after('total_fee_inspection')->default(0)->unsigned()->nullable(); // Tiền ship vận chuyển nội địa trả lại
            $table->text('order_detail_change')->after('amount_vn')->nullable(); // List sản phẩm thay đổi
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn([
                'order_status',
                'order_percent',
                'total_money_item',
                'total_fee_service',
                'total_fee_inspection',
                'china_shipping_cost',
                'order_detail_change',
            ]);
        });
    }
}
