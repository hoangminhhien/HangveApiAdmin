<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderCostRealTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_cost_real', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id')->comment('id đơn hàng');
            $table->string('order_code')->comment('mã đơn hàng');
            $table->string('order_code_po')->nullable()->comment('mã đặt hàng');
            $table->integer('staff_order_id')->nullable()->comment('nhân viên đặt hàng');
            $table->string('staff_order_name')->nullable()->comment('tên nhân viên đặt hàng');
            $table->string('file')->nullable()->comment('hình ảnh');
            $table->decimal('cost_old', 20)->default(0)->comment('tổng thực tế cũ');
            $table->decimal('cost_new', 20)->default(0)->comment('tổng thực tế mới');
            $table->text('note')->nullable();
            $table->tinyInteger('status')->default(1)->comment('1: chờ xác nhận, 2: đã xác nhận');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_cost_real');
    }
}
