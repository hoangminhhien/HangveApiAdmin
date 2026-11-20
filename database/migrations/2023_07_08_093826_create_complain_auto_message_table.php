<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComplainAutoMessageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('complain_auto_message', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('staff_id');
            $table->string('content')->comment('Nội dung tin nhắn');
            $table->string('title')->comment('Tiêu đề tin nhắn');
            $table->softDeletes();
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
        Schema::dropIfExists('complain_auto_message');
    }
}
