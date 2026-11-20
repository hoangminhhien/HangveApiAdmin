<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoFilesToOrderCostRealTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_cost_real', function (Blueprint $table) {
            $table->text('files')->after('file')->nullable();
            $table->text('thuongdo_files')->after('files')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_cost_real', function (Blueprint $table) {
            $table->dropColumn([
                'files',
                'thuongdo_files',
            ]);
        });
    }
}
