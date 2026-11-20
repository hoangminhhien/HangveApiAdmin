<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatAisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chat_ais', function (Blueprint $table) {
            $table->id();
            $table->integer('organization_id')->default(0)->unsigned();

            $table->integer('user_id')->default(0)->unsigned(); // Nhân viên nào
            $table->string('user_code')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_image')->nullable();

            $table->integer('order_id')->default(0)->unsigned();
            $table->string('order_code')->nullable();

            $table->text('summary')->nullable();
            $table->string('attribute_name')->nullable();
            $table->text('options')->nullable();
            $table->mediumText('items')->nullable();
            $table->mediumText('items_cache')->nullable();
            $table->text('item_ids')->nullable();
            $table->integer('rep_id_new')->default(0)->unsigned();

            $table->dateTime('time_last_send')->nullable();
            $table->string('content_last_send')->nullable();

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
        Schema::dropIfExists('chat_ais');
    }
}
