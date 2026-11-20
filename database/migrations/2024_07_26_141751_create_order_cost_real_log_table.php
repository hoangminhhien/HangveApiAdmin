<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderCostRealLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_cost_real_log', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->comment('mã người cập nhật');
            $table->string('user_name')->comment('tên người cập nhật');
            $table->integer('order_cost_real_id')->comment('mã yêu cầu thanh toán thực tế ncc');
            $table->string('order_code')->comment('mã đơn hàng');
            $table->decimal('cost_old', 20)->comment('tổng thực tế cũ');
            $table->decimal('cost_new', 20)->comment('tổng thực tế mới');
            $table->text('note')->nullable()->comment('ghi chú');
            $table->tinyInteger('status')->comment('trạng thái');
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
        Schema::dropIfExists('order_cost_real_log');
    }
}
