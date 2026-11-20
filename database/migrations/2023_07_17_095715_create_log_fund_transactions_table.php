<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogFundTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('log_fund_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->text('fund_transactions');
            $table->string('name')->comment('Tên thao tác tại bảng đó');
            $table->integer('user_id')->comment('user id thực hiện');
            $table->string('user_name')->comment('tên người thực hiện');
            $table->string('user_email')->comment('email của người thực hiện');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('log_fund_transactions');
    }
}
