<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoIdToOrderDetailPackagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_detail_packages', function (Blueprint $table) {
            $table->string('thuongdo_sync')->after('quantity')->nullable(); //
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_detail_packages', function (Blueprint $table) {
            $table->dropColumn([
                'thuongdo_sync',
            ]);
        });
    }
}
