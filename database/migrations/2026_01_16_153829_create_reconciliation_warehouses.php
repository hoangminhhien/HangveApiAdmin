<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReconciliationWarehouses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reconciliation_warehouses', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id')->nullable();
            $table->string('customer_code')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_code')->nullable();
            $table->integer('order_package_id')->nullable();
            $table->tinyInteger('order_package_type')->default(1)->comment('1 là đơn order, 2 là đơn ký gửi');
            $table->string('order_code')->nullable();
            $table->string('bill_code')->nullable()->comment('Mã vận đơn');
            $table->string('status')->nullable();
            $table->integer('type')->nullable()->comment('Loại xuất kho');
            $table->string('warehouse_code')->nullable()->comment('Kho trả hàng');
            $table->string('print_receipt_code')->nullable()->comment('Mã phiếu in');
            $table->decimal('transport_fee')->default(0)->comment('Phí vận chuyển');
            $table->decimal('other_fee')->default(0)->comment('Phí khác');
            $table->decimal('total_amount', 10, 2)->default(0)->comment('Phí khác');
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
        Schema::dropIfExists('reconciliation_warehouses');
    }
}
