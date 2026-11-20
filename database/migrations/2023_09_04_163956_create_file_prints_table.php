<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFilePrintsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('file_prints', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type')->nullable(); // Phân biệt phiếu in của màn nào
            $table->integer('user_id')->nullable(); // Người tạo phiếu in
            $table->integer('customer_id')->nullable(); // Khách hàng
            $table->text('data_json')->nullable(); // Data
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
        Schema::dropIfExists('file_prints');
    }
}
