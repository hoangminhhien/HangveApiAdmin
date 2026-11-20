<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index()->nullable();
            $table->tinyInteger('status')->default(0)->comment('0: chờ xử lý, 1: đã xử lý, 2: hủy bỏ');
            $table->tinyInteger('type_refund')->default(0)->comment('1: tạo hoàn tiền, 2: tạo hủy đơn');
            $table->text('images_create_cancel')->nullable();
            $table->text('images_create_refund')->nullable();
            $table->string('reason_create_refund')->nullable();
            $table->string('reason_create_cancel')->nullable();
            $table->string('reason_cancel')->nullable();
            $table->decimal('china_shipping_cost_refund', 20)
                ->default(0)->comment('Phí vc TQ khi tạo yêu cầu hoàn');
            $table->text('price_quantity_order_details')->nullable()->comment('Đơn giá TQ tính toán khi hoàn');
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
        Schema::dropIfExists('order_refunds');
    }
}
