<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderDetailHistories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_detail_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('order_detail_id')->comment('id chi tiết đơn');
            $table->string('object')->comment('đối tượng: giá, số lượng');
            $table->string('value')->comment('giá trị thay đổi');
            $table->integer('user_id');
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
        Schema::dropIfExists('order_detail_histories');
    }
}
