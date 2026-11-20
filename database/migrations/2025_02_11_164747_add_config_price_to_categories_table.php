<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConfigPriceToCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->integer('hn_kg')->after('thuongdo_name_en')->default(0)->nullable();
            $table->integer('hn_m3')->after('hn_kg')->default(0)->nullable();
            $table->integer('hcm_kg')->after('hn_m3')->default(0)->nullable();
            $table->integer('hcm_m3')->after('hcm_kg')->default(0)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'hn_kg',
                'hn_m3',
                'hcm_kg',
                'hcm_m3',
            ]);
        });
    }
}
