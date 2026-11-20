<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogPingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('log_pings', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('source')->default(1); // 1- 9pay
            $table->tinyInteger('type')->default(1); // Log hay là đánh dấu reset
            $table->integer('user_id')->default(0)->unsigned(); // Ai là người tạo lệnh reset nếu có
            $table->dateTime('time')->nullable(); // Thời gian reset hoặc thời gian log tùy thuộc vào type
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
        Schema::dropIfExists('log_pings');
    }
}
