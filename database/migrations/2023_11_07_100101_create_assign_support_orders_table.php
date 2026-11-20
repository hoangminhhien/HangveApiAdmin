<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Database\Migrations\Common\BaseMigration as Migration;

class CreateAssignSupportOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('assign_support_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('organization_id');

            $table->string('order_no')->nullable()->comment('Mã đặt hàng, đặt hàng mới fill vào');
            $table->integer('order_id')->comment('Mã đơn tổng orders.id');
            $table->integer('order_detail_id')->comment('Mã đơn theo sản phẩm order_details.id');
            $table->integer('assigner_id')->nullable()->comment('Người nhờ đặt đơn hộ users.id');
            $table->integer('assignee_id')->nullable()->comment('Người được nhờ đặt đơn hộ users.id');
            $table->integer('quantity')->comment('Số lượng sản phẩm nhờ đặt hộ');
            $table->string('status')
                ->nullable()
                ->comment('Trạng thái ncc, order_buyders.supplier_status');
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
        Schema::dropIfExists('assign_support_orders');
    }
}
