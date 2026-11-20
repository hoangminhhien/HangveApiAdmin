<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoIdToOrderNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_notes', function (Blueprint $table) {
            $table->integer('thuongdo_id')->after('is_public')->default(0)->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_notes', function (Blueprint $table) {
            $table->dropColumn([
                'thuongdo_id',
            ]);
        });
    }
}
