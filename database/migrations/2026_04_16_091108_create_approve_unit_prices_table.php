<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApproveUnitPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('approve_unit_prices', function (Blueprint $table) {
            $table->id();
            $table->integer('order_package_id')->default(0)->index();
            $table->integer('customer_id')->default(0)->index();
            $table->integer('user_id')->default(0)->index()->comment('Người tạo yêu cầu');
            $table->dateTime('approve_date')->nullable()->comment('Ngày duyệt');
            $table->integer('approve_user_id')->default(0)->comment('ID người duyệt bên hangve');
            $table->string('request_reason')->nullable()->comment('Lý do yêu cầu');
            $table->string('cancel_reason')->nullable()->comment('Lý do hủy yêu cầu');
            $table->tinyInteger('status')->default(0)->comment('Trạng thái');
            $table->integer('unit_price')->default(0)->comment('Đơn giá');
            $table->string('unit')->nullable()->comment('Đơn vị tính');
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
        Schema::dropIfExists('approve_unit_prices');
    }
}
