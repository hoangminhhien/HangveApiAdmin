<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLevelRateToCustomerOffersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_offers', function (Blueprint $table) {
            $table->tinyInteger('level_rate')->default(0)->after('deposit_rate'); // Admin set cấp độ cho người dùng luôn
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
            $table->dropColumn([
                'level_rate',
            ]);
        });
    }
}
