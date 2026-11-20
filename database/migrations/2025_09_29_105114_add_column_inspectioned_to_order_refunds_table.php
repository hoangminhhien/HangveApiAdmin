<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnInspectionedToOrderRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->tinyInteger('inspectioned')->default(0)->comment('Đã sử dụng dịch vụ kiểm đếm check với TH hủy đơn');
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
            $table->dropColumn([
                'inspectioned'
            ]);
        });
    }
}
