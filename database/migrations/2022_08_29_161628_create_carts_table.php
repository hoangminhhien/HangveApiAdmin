<?php

use App\Constants\ServiceConstant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCartsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('supplier_id');
            $table->integer('customer_id');
            $table->decimal('total_amount_cny')->default(0)->comment('Tổng giá trị đơn hàng của nhà cung cấp theo tiền TQ');
            $table->tinyInteger('is_inspection')->default(0)->comment('Kiểm hóa');
            $table->tinyInteger('is_woodworking')->default(0)->comment('Đóng gỗ');
            $table->tinyInteger('is_shock_proof')->default(0)->comment('Chống shock');
            $table->enum('delivery_type', ServiceConstant::DELIVERIES)->default('normal')->comment('Vận chuyển: nhanh, bình thường');
            $table->string('note', 500)->nullable()->comment('Ghi chú cho tổ chức hoặc công ty');
            $table->integer('warehouse_id')->nullable()->comment('Kho nhận hàng');
            $table->integer('customer_delivery_id')->nullable()->comment('Thông tin mua hàng');
            $table->integer('organization_id')->comment('Tổ chức');
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
        Schema::dropIfExists('carts');
    }
}
