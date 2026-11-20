<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldUserGroupIdReportOrderConsignment extends Migration
{
    public function up()
    {
        Schema::table('report_order_consignments', function (Blueprint $table) {
            $table->integer('user_group_id')->index()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_order_consignments', function (Blueprint $table) {
            $table->dropColumn(['user_group_id']);
        });
    }
}
