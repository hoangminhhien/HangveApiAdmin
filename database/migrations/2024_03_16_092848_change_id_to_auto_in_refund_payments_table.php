<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeIdToAutoInRefundPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::table('refund_payments', function (Blueprint $table) {
            $table->increments('id')->change();
        });
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('refund_payments', function (Blueprint $table) {
            $table->integer('id')->change();
        });
    }
}
