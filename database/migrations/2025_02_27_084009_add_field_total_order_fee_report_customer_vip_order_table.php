<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldTotalOrderFeeReportCustomerVipOrderTable extends Migration
{
    public function up()
    {
        Schema::table('report_customer_vip_orders', function (Blueprint $table) {
            $table->decimal('total_fee', 20)->default(0)->nullable();
            $table->decimal('total_fee_new', 20)->default(0)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_customer_vip_orders', function (Blueprint $table) {
            $table->dropColumn([
                'total_fee', 'total_fee_new'
            ]);
        });
    }
}
