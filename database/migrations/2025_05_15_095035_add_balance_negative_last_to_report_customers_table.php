<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBalanceNegativeLastToReportCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_customers', function (Blueprint $table) {
            $table->dateTime('blance_negative_last')->after('balance_amount')->nullable();
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
                'blance_negative_last',
            ]);
        });
    }
}
