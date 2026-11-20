<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderDepositPercentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_deposit_percents', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id')->default(0)->index();
            $table->integer('deposit_percent_old')->default(0)->comment('Phần trăm đặt cũ');
            $table->integer('deposit_percent_new')->default(0)->comment('Phần trăm đặt cũ');
            $table->tinyInteger('status')->default(0)->comment('Đã thực hiện cầu này hay chưa');
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
        Schema::dropIfExists('order_deposit_percents');
    }
}
