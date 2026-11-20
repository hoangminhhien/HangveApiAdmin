<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerResetStaffTimesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_reset_staff_times', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('customer_id');
            $table->string('reset_staff')->nullable(); // 'care', 'counselor'
            $table->integer('reset_staff_time')->default(0)->unsigned()->nullable(); // Thời gian mà thực hiện việc chuyển đổi
            $table->string('source')->nullable(); // Nguồn là do tự động 5 đơn, hay do cron
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
        Schema::dropIfExists('customer_reset_staff_times');
    }
}
