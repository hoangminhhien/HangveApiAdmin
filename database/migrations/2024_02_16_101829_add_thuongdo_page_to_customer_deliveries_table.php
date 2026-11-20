<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoPageToCustomerDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_deliveries', function (Blueprint $table) {
            $table->integer('thuongdo_page')->after('thuongdo_id')->default(0)->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'thuongdo_page',
            ]);
        });
    }
}
