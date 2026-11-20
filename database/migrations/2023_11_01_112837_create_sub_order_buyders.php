<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Database\Migrations\Common\BaseMigration as Migration;

class CreateSubOrderBuyders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('order_buyder_subs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('organization_id');

            $table->integer('staff_id')->comment('Nhân viên phụ trách đơn, users.id');
            $table->integer('order_id')->comment('orders.id, dùng để lấy danh sách cho nhanh');
            $table->integer('order_detail_id')->comment('order_details.id');
            $table->integer('order_buyder_id')->comment('Mã đơn hàng cha order_buyders.id');
            $table->string('buyder_status')
                ->default(\App\Constants\Buyder\BuyderStatusConstant::BUYDER_ORDER_WAITING_PAY)
                ->comment('Trạng thái đơn hàng nhận từ Buyder, null khi đặt hàng, chạy cron cập nhật');
            $table->string('order_no')->comment('Mã đặt hàng, chính là order_buyders.order_no');
            $table->string('buyder_sub_id')->comment('Mã đặt hàng con (VOS)');
            $table->string('quantity')->comment('Số lượng của đơn con');
            $table->unsignedDecimal('purchase_amount', 10, 2, true)->comment('Tổng giá tiền của một đơn con');
            $table->unsignedDecimal('domestic_post_fee', 10, 2, true)->nullable()
                ->comment('Phí vận chuyển nội địa, null khi tạo đơn, chạy cron cập nhật');
            $table->string('taobao_purchase_id')->comment('Thanh toán Taobao');
            $table->string('taobao_sub_purchase_id')->comment('Thanh toán Taobao');
            $table->string('mp_id')->comment('Mã sản phẩm');
            $table->string('mp_sku_id')->comment('Mã sku sản phẩm');
            $table->tinyInteger('close_reason')->nullable()->comment('Lý do hủy đơn');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_order_buyders');
    }
}
