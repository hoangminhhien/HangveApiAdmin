<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerPointTimesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_point_times', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id')->default(0)->unsigned()->nullable(); // Của khách hàng nào
            $table->tinyInteger('type')->default(1)->nullable(); // Loại point gì ? 1-point_way, 2-point_level, 3-point_care
            $table->integer('case')->default(0)->nullable(); // Case cụ thể của type trên: 1001-Tạo tài khoản, 1002-.....
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
        Schema::dropIfExists('customer_point_times');
    }
}
