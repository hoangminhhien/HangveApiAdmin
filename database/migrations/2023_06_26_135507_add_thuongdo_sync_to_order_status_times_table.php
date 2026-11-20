<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoSyncToOrderStatusTimesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_status_times', function (Blueprint $table) {
            $table->string('thuongdo_sync')->after('order_id')->nullable(); // Được sinh ra khi đồng bộ order_id nào ben thuongdo
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_status_times', function (Blueprint $table) {
            $table->dropColumn([
                'thuongdo_sync',
            ]);
        });
    }
}
