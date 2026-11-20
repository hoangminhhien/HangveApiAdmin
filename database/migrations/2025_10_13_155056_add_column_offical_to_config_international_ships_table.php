<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnOfficalToConfigInternationalShipsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('config_international_ships', function (Blueprint $table) {
            $table->tinyInteger('official')->default(1)->after('status')->comment('1. Bán chính ngạch, 2. Chính ngạch');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('config_international_ships', function (Blueprint $table) {
            $table->dropColumn([
                'official'
            ]);
        });
    }
}
