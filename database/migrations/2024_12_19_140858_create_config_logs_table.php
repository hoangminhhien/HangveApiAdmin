<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConfigLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('config_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('type')->comment('Loại log');
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
        Schema::dropIfExists('config_logs');
    }
}
