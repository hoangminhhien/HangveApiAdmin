<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('organization_id')->nullable();
            $table->integer('staff_id')->comment('Người tạo');
            $table->integer('order_id')->comment('Id đơn hàng');
            $table->string('order_code')->comment('Mã đơn hàng');
            $table->double('amount_cn')->comment('Số tiền hoàn cho khách tiền trung');
            $table->decimal('exchange_rate', 20)->comment('Tỉ giá');
            $table->double('amount_vn')->comment('Số tiền hoàn cho khách tiền trung');
            $table->string('file')->nullable()->comment('File đính kèm');
            $table->string('note')->nullable()->comment('ghi chú');
            $table->string('status')->default("status_1")->comment('Trạng thái hoàn tiền');
            $table->string('type')->default(1)->comment('Loại hoàn tiền');
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
        Schema::dropIfExists('refunds');
    }
}
