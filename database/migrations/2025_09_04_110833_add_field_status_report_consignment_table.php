<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldStatusReportConsignmentTable extends Migration
{
    public function up()
    {
        Schema::table('report_consignments', function (Blueprint $table) {
            $table->integer('status_4')->default(0)->nullable()->comment('Chờ báo giá');
            $table->integer('status_5')->default(0)->nullable()->comment('Chờ đặt cọc');
            $table->integer('status_6')->default(0)->nullable()->comment('Đã đặt cọc');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_consignments', function (Blueprint $table) {
            $table->dropColumn([
                'status_4',
                'status_5',
                'status_6'
            ]);
        });
    }
}
