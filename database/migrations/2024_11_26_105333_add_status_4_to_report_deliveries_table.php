<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatus4ToReportDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_deliveries', function (Blueprint $table) {
            $table->integer('status_4')->after('status_3')->default(0)->nullable()->comment('Đang giao hàng');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'status_4',
            ]);
        });
    }
}
