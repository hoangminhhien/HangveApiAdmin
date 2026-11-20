<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRefundPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('refund_payments', function (Blueprint $table) {
            $table->id();
            $table->integer('refund_id');
            $table->string('unit_currency')->default(1)->comment('Đơn vị tiền tệ');
            $table->double('amount')->default(0)->comment('Số tiền');
            $table->string('note')->comment('Ghi chú');
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
        Schema::dropIfExists('refund_payments');
    }
}
