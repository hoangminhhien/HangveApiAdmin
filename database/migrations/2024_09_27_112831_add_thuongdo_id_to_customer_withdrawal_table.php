<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoIdToCustomerWithdrawalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_withdrawal', function (Blueprint $table) {
            $table->integer('thuongdo_id')->after('image')->default(0)->unsigned()->nullable();
            $table->integer('customer_bank_id')->after('code')->default(0)->unsigned()->nullable();
            $table->integer('bank_id')->after('customer_bank_id')->default(0)->unsigned()->nullable();
            $table->string('bank_name')->after('bank_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_withdrawal', function (Blueprint $table) {
            $table->dropColumn([
                'thuongdo_id',
                'customer_bank_id',
                'bank_id',
                'bank_name',
            ]);
        });
    }
}
