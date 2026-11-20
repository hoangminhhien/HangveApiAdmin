<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldStaffGroupIdReportCustomerOrders extends Migration
{
    public function up()
    {
        Schema::table('report_customer_orders', function (Blueprint $table) {
            $table->bigInteger('staff_group_id')->after('id')->nullable()->index();
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
            $table->dropColumn('staff_group_id');
        });
    }
}
