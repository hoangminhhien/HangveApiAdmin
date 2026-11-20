<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            // $table->id();

            $table->increments('id');

            $table->integer('group')->default(0)->unsigned()->nullable(); // Nhóm danh mục
            $table->integer('name')->default(0)->unsigned()->nullable(); // Tên loại thông báo
            $table->integer('user_id')->nullable(); // Ai nhận thông báo này
            $table->text('data')->nullable(); // Nội dung
            $table->integer('time_create')->default(0)->unsigned()->nullable(); // Thời gian tạo
            $table->integer('time_seen')->default(0)->unsigned()->nullable(); // Xem lúc nào

            $table->integer('organization_id')->nullable();

            $table->tinyInteger('status')->default(1); // Dùng để ẩn hiện bản ghi
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
        Schema::dropIfExists('notifications');
    }
}
