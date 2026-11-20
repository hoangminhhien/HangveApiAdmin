<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoIdToSuppliersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('thuongdo_id')->after('industry')->nullable();
            $table->string('thuongdo_shop_id')->after('thuongdo_id')->nullable();
            $table->string('thuongdo_name_en')->after('thuongdo_shop_id')->nullable();
            $table->string('thuongdo_address_en')->after('thuongdo_name_en')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'thuongdo_id',
                'thuongdo_shop_id',
                'thuongdo_name_en',
                'thuongdo_address_en',
            ]);
        });
    }
}
