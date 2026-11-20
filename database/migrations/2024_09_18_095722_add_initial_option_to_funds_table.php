<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInitialOptionToFundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('funds', function (Blueprint $table) {
            $table->double('initial_total_money_in')->after('initial_balance')->default(0)->nullable(); // Số tiền ban đầu
            $table->double('initial_total_money_out')->after('initial_total_money_in')->default(0)->nullable(); // Số tiền ra ban đầu
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('funds', function (Blueprint $table) {
            $table->dropColumn([
                'initial_total_money_in',
                'initial_total_money_out'
            ]);
        });
    }
}
