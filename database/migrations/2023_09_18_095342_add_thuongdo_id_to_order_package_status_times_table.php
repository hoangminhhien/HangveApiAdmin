<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoIdToOrderPackageStatusTimesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_package_status_times', function (Blueprint $table) {
            $table->string('thuongdo_id')->after('key')->nullable();
            $table->string('thuongdo_uid')->after('thuongdo_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_package_status_times', function (Blueprint $table) {
            $table->dropColumn([
                'thuongdo_id',
                'thuongdo_uid',
            ]);
        });
    }
}
