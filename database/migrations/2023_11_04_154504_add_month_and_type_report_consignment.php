<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMonthAndTypeReportConsignment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_order_consignments', function (Blueprint $table) {
            $table->tinyInteger('month')->after('week')->nullable()->index()->comment('Tháng');
            $table->string('type')->after('month')->nullable()->index()->comment('Loại');
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
            $table->dropColumn([
                'month',
                'type'
            ]);
        });
    }
}
