<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoHiddenToTransportersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transporters', function (Blueprint $table) {
            $table->tinyInteger('thuongdo_hidden')->after('thuongdo_id')->default(0)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transporters', function (Blueprint $table) {
            $table->dropColumn([
                'thuongdo_hidden'
            ]);
        });
    }
}
