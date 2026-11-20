<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexThuongdoIdToThreeNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_supplier_histories', function (Blueprint $table) {
            $table->index('thuongdo_id');
        });
        Schema::table('order_detail_histories', function (Blueprint $table) {
            $table->index('thuongdo_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_supplier_histories', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_id']);
        });
        Schema::table('order_detail_histories', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_id']);
        });

    }
}
