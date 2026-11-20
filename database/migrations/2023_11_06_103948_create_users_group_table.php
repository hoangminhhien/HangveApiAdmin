<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersGroupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users_group', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('group_id');
            $table->integer('user_id');
            $table->integer('customer_id')->nullable();
            $table->string('month')->comment('kpi tháng');
            $table->tinyInteger('kpi')->comment('1: sales, 2: services');
            $table->decimal('kpi_sales', 20)->comment('Mục tiêu doanh số');
            $table->decimal('kpi_services', 20)->comment('Mục tiêu phí dịch vụ');
            $table->decimal('real_sales', 20)->comment('doanh số thực tế');
            $table->decimal('real_services', 20)->comment('phí dịch vụ thực tế');
            $table->integer('total_orders')->default(0)->comment('tổng đơn trong tháng');
            $table->tinyInteger('is_get_customer')->default(1)->comment('1: Yes, 0: No');
            $table->tinyInteger('status')->default(0)->comment('0: Không đạt, 1: Đạt');
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
        Schema::dropIfExists('users_group');
    }
}
