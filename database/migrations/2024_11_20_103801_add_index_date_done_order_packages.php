<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexDateDoneOrderPackages extends Migration
{
    public function up()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->index('date_done');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->dropIndex(['date_done']);
        });
    }
}
