<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_contracts', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id')->nullable();
            $table->string('original_name')->nullable();
            $table->string('path')->nullable();
            $table->integer('creator_id')->default(0);
            $table->dateTime('signing_time')->nullable()->comment('Thời gian ký');
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
        Schema::dropIfExists('order_contracts');
    }
}
