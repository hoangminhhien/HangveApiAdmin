<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMoneyRefundFromXadalToOrderRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->decimal('money_refund_from_xadal', 15, 2)->default(0)->comment('Số tiền hoàn từ xadal');
            $table->string('order_code_from_xadal')->nullable()->comment('Mã đặt hàng từ xadal');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->dropColumn('money_refund_from_xadal');
            $table->dropColumn('order_code_from_xadal');
        });
    }
}
