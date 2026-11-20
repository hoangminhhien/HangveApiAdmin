<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnDebtToCustomerOffersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_offers', function (Blueprint $table) {
            $table->tinyInteger('debt')->default(0)->comment('Công nợ');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_offers', function (Blueprint $table) {
            $table->dropColumn(['debt']);
        });
    }
}
