<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMoneyRefundCnOriginToOrderRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->decimal('money_refund_cn_origin', 8,2)->default(0)->comment('Tiền hoàn tiền tệ gốc');
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
            $table->dropColumn('money_refund_cn_origin');
        });
    }
}
