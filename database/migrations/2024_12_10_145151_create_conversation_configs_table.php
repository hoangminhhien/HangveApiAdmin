<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversationConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conversation_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(); // Tên trạng thái
            $table->string('color')->nullable(); // Màu sắc
            $table->integer('time')->default(0)->unsigned()->nullable(); // Số giây chờ khách phản hồi
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
        Schema::dropIfExists('conversation_configs');
    }
}
