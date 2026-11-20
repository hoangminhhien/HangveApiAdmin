<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatus18ToReportPackagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_packages', function (Blueprint $table) {
            // $table->integer('status_18')->after('status_17')->default(0)->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_packages', function (Blueprint $table) {
            $table->dropColumn([
                'status_18',
            ]);
        });
    }
}
