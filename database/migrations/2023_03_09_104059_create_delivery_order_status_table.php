<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeliveryOrderStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delivery_order_status', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_id')->comment('Mã giao hàng');
            $table->string('carrier')->comment('Đơn vị vận chuyển');
            $table->string('desc')->comment('Mô tả');
            $table->string('shop_id');
            $table->longText('response');
            $table->integer('status')->comment('Trạng thái đơn hàng');
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
        Schema::dropIfExists('delivery_order_status');
    }
}
