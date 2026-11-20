<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoIdToReportCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_customers', function (Blueprint $table) {
            $table->integer('thuongdo_id')->after('discount_amount')->default(0)->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_customers', function (Blueprint $table) {
            $table->dropColumn([
                'thuongdo_id',
            ]);
        });
    }
}
