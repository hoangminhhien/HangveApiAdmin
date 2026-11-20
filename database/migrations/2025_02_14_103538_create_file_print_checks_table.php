<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFilePrintChecksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('file_print_checks', function (Blueprint $table) {
            $table->id();
            $table->integer('file_print_id')->default(0)->nullable();
            $table->integer('user_id')->default(0)->nullable();
            $table->integer('order_package_id')->default(0)->nullable();
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
        Schema::dropIfExists('file_print_checks');
    }
}
