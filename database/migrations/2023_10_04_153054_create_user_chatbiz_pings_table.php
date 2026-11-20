<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserChatbizPingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_chatbiz_pings', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('user_id')->nullable(); // Nhân viên nào
            $table->string('user_code')->nullable(); // Nhân viên nào
            $table->string('user_name')->nullable(); // Nhân viên nào
            $table->date('date')->nullable(); // Ngày nào

            // https://docs.google.com/spreadsheets/d/1HWx0tnh-8TWYIkrMb7W0pYJ82yUF5t2dqVnS9Qv33Sg/edit#gid=0

            $table->integer('phone_call_out')->default(0)->unsigned()->nullable(); // Tổng Gọi ra
            $table->integer('phone_call_out_success')->default(0)->unsigned()->nullable(); // Tổng Gọi ra thành công
            $table->integer('phone_call_in')->default(0)->unsigned()->nullable(); // Tổng Gọi vào
            $table->integer('phone_call_in_success')->default(0)->unsigned()->nullable();  // Tổng Gọi vào thành công
            $table->text('phone_call_out_list')->nullable(); // Danh sách khách gọi vào
            $table->text('phone_call_in_list')->nullable(); // Danh sách khách gọi ra

            $table->integer('zalo_handle')->default(0)->unsigned()->nullable(); // Xử lý
            $table->integer('zalo_done')->default(0)->unsigned()->nullable(); // Hoàn thành
            $table->integer('zalo_late')->default(0)->unsigned()->nullable(); // Trễ
            $table->text('zalo_handle_list')->nullable();
            $table->text('zalo_done_list')->nullable();
            $table->text('zalo_late_list')->nullable();

            $table->integer('facebook_handle')->default(0)->unsigned()->nullable(); // Xử lý
            $table->integer('facebook_done')->default(0)->unsigned()->nullable(); // Hoàn thành
            $table->integer('facebook_late')->default(0)->unsigned()->nullable(); // Trễ
            $table->text('facebook_handle_list')->nullable();
            $table->text('facebook_done_list')->nullable();
            $table->text('facebook_late_list')->nullable();

            $table->integer('livechat_handle')->default(0)->unsigned()->nullable(); // Xử lý
            $table->integer('livechat_done')->default(0)->unsigned()->nullable(); // Hoàn thành
            $table->integer('livechat_late')->default(0)->unsigned()->nullable(); // Trễ
            $table->text('livechat_handle_list')->nullable();
            $table->text('livechat_done_list')->nullable();
            $table->text('livechat_late_list')->nullable();

            $table->integer('total_customer')->default(0)->unsigned()->nullable(); // Tổng khách hàng của toàn bộ các nhóm (unique)

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
        Schema::dropIfExists('user_chatbiz_pings');
    }
}
