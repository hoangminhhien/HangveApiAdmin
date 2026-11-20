<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldReportConsignmentDoneByDate extends Migration
{
    public function up()
    {
        Schema::table('report_order_consignment_date', function (Blueprint $table) {
            $table->string('type', 20)->after('date')->nullable()
                ->comment('Loại báo cáo')->index();
            $table->string('week', 10)->after('date')->nullable()->index();
            $table->string('month', 10)->after('date')->nullable()->index();
            $table->string('year', 10)->after('date')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_order_consignment_date', function (Blueprint $table) {
            $table->dropColumn([
                'type', 'week', 'month', 'year'
            ]);
        });
    }
}
