<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->integer('organization_id')->default(0)->unsigned()->nullable();

            $table->integer('conversation_id')->default(0)->unsigned()->nullable();
            $table->tinyInteger('source')->default(0)->nullable(); // Ai gửi
            $table->tinyInteger('type')->default(0)->nullable(); // Loại tin nhắn
            $table->text('content')->nullable();
            $table->integer('parent_id')->default(0)->unsigned()->nullable(); // Tin nhắn cha là gì

            // $table->dateTime('time_seen')->nullable();
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
        Schema::dropIfExists('messages');
    }
}
