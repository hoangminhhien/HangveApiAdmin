<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Constants\OrderConstant;

class CreateOrderAdvanceMoneysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_advance_moneys', function (Blueprint $table) {
            $table->increments('id');
            $table->nullableMorphs('order');
            $table->string('order_code');
            $table->integer('customer_id');
            $table->integer('staff_id')->comment('Người tạo');
            $table->integer('manager_id')->nullable()->comment('Người xử lý');
            $table->decimal('amount', 20)->default(0)->comment('Tổng tiền ứng');
            $table->string('status', 20)->default(OrderConstant::KEY_ORDER_ADVANCE_STATUS_WAITING)->comment('Trạng thái theo Key');
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
        Schema::dropIfExists('order_advance_moneys');
    }
}
