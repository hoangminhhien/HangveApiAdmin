<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKpiTransportsToGroupKpiMonthsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('group_kpi_months', function (Blueprint $table) {
            $table->decimal('kpi_transports', 20)->comment('Mục tiêu doanh số');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('group_kpi_months', function (Blueprint $table) {
            $table->dropColumn('kpi_transports');
        });
    }
}
