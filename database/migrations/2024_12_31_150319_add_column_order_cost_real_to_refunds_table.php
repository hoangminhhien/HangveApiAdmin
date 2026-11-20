<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnOrderCostRealToRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->double('order_cost_real_old')->after('amount_vn')->comment('Tiền hàng thực tế ban đầu');
            $table->double('order_cost_real')->after('order_cost_real_old')->comment('Tiền hàng thực tế muốn cập nhật');
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
                'order_cost_real_old',
                'order_cost_real'
            ]);
        });
    }
}
