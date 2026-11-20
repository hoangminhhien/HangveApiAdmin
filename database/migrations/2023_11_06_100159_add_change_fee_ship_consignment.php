<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddChangeFeeShipConsignment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_order_consignments', function (Blueprint $table) {
            $table->decimal('fee_ship', 20)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        /*Schema::table('report_order_consignments', function (Blueprint $table) {
            $table->dropColumn([
                'fee_ship'
            ]);
        });*/
    }
}
