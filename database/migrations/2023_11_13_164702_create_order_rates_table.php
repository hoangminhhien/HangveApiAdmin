<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_rates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('order_code')->unique()->comment('Mã đơn hàng');
            $table->string("customer_code")->comment("Mã khách hàng");
            $table->tinyInteger("sale_staff_rate")->default(0)->comment("Đánh giá nhân viên kinh doanh 1-5 sao");
            $table->tinyInteger("order_staff_rate")->default(0)->comment("Đánh giá nhân viên đặt hàng 1-5 sao");
            $table->tinyInteger("transport_rate")->default(0)->comment("Đánh giá vận chuyển 1-5 sao");
            $table->text("comment")->nullable();
            $table->date("date");
            $table->timestamps();
            $table->softDeletes();
            $table->index("date");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_rates');
    }
}
