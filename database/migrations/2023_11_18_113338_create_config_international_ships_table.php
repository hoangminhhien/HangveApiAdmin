<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConfigInternationalShipsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('config_international_ships', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('organization_id')->nullable();

            $table->string('type')->nullable(); // Loại cấu hình hình kg hay m3
            $table->dateTime('date_start')->nullable(); // Thời gian bắt đầu

            $table->text('matrix_price')->nullable(); // Ma trận giá
            $table->text('list_province')->nullable(); // Danh sách các tỉnh áp dụng

            $table->tinyInteger('status')->default(1); // Trạng thái
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
        Schema::dropIfExists('config_international_ships');
    }
}
