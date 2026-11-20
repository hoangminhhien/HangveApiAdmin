<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoIdToCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('thuongdo_id')->after('organization_id')->nullable();
            $table->string('thuongdo_name_china')->after('thuongdo_id')->nullable();
            $table->string('thuongdo_name_en')->after('thuongdo_name_china')->nullable();
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
                'thuongdo_id',
                'thuongdo_name_china',
                'thuongdo_name_en',
            ]);
        });
    }
}
