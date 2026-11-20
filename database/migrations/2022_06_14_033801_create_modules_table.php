<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateModulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('type')->default(0)->unsigned()->nullable(); // Mã nhóm do dev fix cứng quy định (Dùng để cần tìm nhóm nào theo mã cho chuẩn)
            $table->integer('priority')->default(0)->nullable(); // Độ ưu tiên để sắp sếp thứ tự
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
        Schema::dropIfExists('modules');
    }
}
