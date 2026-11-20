<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoIdToCustomerDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_deliveries', function (Blueprint $table) {
            $table->string('thuongdo_id')->after('is_default')->nullable();
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
                'thuongdo_id',
            ]);
        });
    }
}
