<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldDateReportCustomerReOrder extends Migration
{
    public function up()
    {
        Schema::table('report_customer_re_orders', function (Blueprint $table) {
            $table->date('date')->index()->nullable()->after('total_customer');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_customer_re_orders', function (Blueprint $table) {
            $table->dropColumn(['date']);
        });
    }
}
