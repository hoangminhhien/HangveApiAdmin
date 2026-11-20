<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoIdToOrderDetailHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_detail_histories', function (Blueprint $table) {
            $table->string('value_old')->after('value')->nullable();
            $table->string('order_status')->after('value_old')->nullable();
            $table->integer('thuongdo_id')->after('user_id')->default(0)->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_detail_histories', function (Blueprint $table) {
            $table->dropColumn([
                'value_old',
                'order_status',
                'thuongdo_id',
            ]);
        });
    }
}
