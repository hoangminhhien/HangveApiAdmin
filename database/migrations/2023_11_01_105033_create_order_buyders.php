<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Database\Migrations\Common\BaseMigration as Migration;

class CreateOrderBuyders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('order_buyders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('organization_id');

            $table->integer('order_id')->comment('Mã đơn hàng Thương Đô, orders.id');
            $table->string('buyder_id')->comment('Trả về từ Buyder khi tạo đơn, data.id');
            $table->string('buyder_no')->comment('Trả về từ Buyder khi tạo đơn, data.orderNo');
            $table->string('supplier_status')
                ->default(\App\Constants\Buyder\BuyderStatusConstant::BUYDER_ORDER_WAITING_PAY)
                ->comment('Trạng thái NCC, lúc tạo đơn chưa có nhưng sẽ lấy lại sau trong API danh sách');
            $table->integer('creator_id')->comment('Người tạo, users.id');
            $table->string('order_source')
                ->default(\App\Constants\Buyder\OrderBuyderConstant::HANGVE)
                ->comment('Nền tảng thương mại điện từ người mua');
            $table->unsignedDecimal('purchase_amount', 10, 2)
                ->default(0)
                ->comment('Số tiền dự kiến');
            $table->text('receiver')->nullable()->comment('Thông tin người mua');
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
        Schema::dropIfExists('order_buyders');
    }
}
