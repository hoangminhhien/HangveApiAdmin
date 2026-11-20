<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnSourceToReportCustomerVipOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_customer_vip_orders', function (Blueprint $table) {
            $table->tinyInteger('source')->default(0);
        });
        Schema::table('report_customer_orders', function (Blueprint $table) {
            $table->tinyInteger('source')->default(0);
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
            $table->dropColumn('source');
        });
        Schema::table('report_customer_orders', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
}
