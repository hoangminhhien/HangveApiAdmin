<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRepAisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rep_ais', function (Blueprint $table) {
            $table->id();
            $table->integer('organization_id')->default(0)->unsigned();
            $table->integer('chat_id')->default(0)->unsigned();
            $table->tinyInteger('source')->default(0); // Ai gửi
            $table->integer('user_id')->default(0)->unsigned(); // Nhân viên nào
            $table->tinyInteger('type')->default(0); // Loại tin nhắn
            $table->tinyInteger('action')->default(0); // Là câu hỏi hay gì
            $table->text('content')->nullable();
            $table->integer('parent_id')->default(0)->unsigned(); // Tin nhắn cha là gì
            $table->integer('clear')->default(0)->unsigned(); // Dùng tin nhắn này để vào lịch sử
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
        Schema::dropIfExists('rep_ais');
    }
}
