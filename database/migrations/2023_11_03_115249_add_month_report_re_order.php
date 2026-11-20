<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMonthReportReOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_customer_re_orders', function (Blueprint $table) {
            $table->tinyInteger('month')->after('week')->nullable()->index()->comment('Tháng');
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
            $table->dropColumn([
                'month',
            ]);
        });
    }
}
