<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldIsXadalOrderBuydersTable extends Migration
{
    public function up()
    {
        Schema::table('order_buyders', function (Blueprint $table) {
            $table->tinyInteger('is_xadal')->after('source')
                ->nullable()
                ->index()
                ->default(0)->comment('0 : chưa chuyển xadal, 1 : đã chuyển');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_buyders', function (Blueprint $table) {
            $table->dropColumn('is_xadal');
        });
    }
}
