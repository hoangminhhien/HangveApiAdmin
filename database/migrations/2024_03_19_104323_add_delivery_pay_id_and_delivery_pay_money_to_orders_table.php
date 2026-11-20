<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeliveryPayIdAndDeliveryPayMoneyToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('delivery_pay_id')->after('delivery_id')->default(0)->unsigned()->nullable(); // Giao dịch nào xác nhận đã xác nhận lấy dc tiền hàng còn thiếu ghi nhận vào đơn
            $table->double('delivery_pay_debt_cost')->after('delivery_pay_id')->default(0)->unsigned()->nullable(); // Số tiền hàng còn thiếu
            $table->double('delivery_pay_shipping_cost')->after('delivery_pay_debt_cost')->default(0)->unsigned()->nullable(); // Số tiền vận chuyển (cái này sẽ cộng của tất cả các giao hàng ứng với đơn hàng này)
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_pay_id',
                'delivery_pay_debt_cost',
                'delivery_pay_shipping_cost',
            ]);
        });
    }
}
