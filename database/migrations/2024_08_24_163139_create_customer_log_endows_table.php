<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerLogEndowsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_log_endows', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id')->index();
            $table->integer('user_id')->comment('Nội dung ghi chú');
            $table->string('type')->comment('Loại ưu đãi');
            $table->string('value_old')->comment('Giá trị cũ');
            $table->string('value_new')->comment('Giá trị mới');
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
        Schema::dropIfExists('customer_log_endows');
    }
}
