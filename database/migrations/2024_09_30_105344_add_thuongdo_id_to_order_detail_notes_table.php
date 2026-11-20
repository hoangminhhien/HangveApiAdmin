<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoIdToOrderDetailNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_detail_notes', function (Blueprint $table) {
            $table->integer('thuongdo_id')->after('content')->default(0)->unsigned()->nullable();
            $table->text('content')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_detail_notes', function (Blueprint $table) {
            $table->dropColumn([
                'thuongdo_id',
            ]);
            $table->string('content')->change();
        });
    }
}
