<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnFilterByCustomerInReportCustomerOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_customer_orders', function (Blueprint $table) {
            $table->string('filter_by_customer')->nullable()->comment('old: KH cũ, new: KH mới')->after('total_order');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_customer_orders', function (Blueprint $table) {
            $table->dropColumn('filter_by_customer');
        });
    }
}
