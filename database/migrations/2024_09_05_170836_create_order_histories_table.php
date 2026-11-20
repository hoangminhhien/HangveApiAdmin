<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_histories', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->index()->nullable();
            $table->integer('user_id')->index()->nullable();
            $table->string('history_type')->index()->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('content')->nullable();
            $table->dateTime('date')->nullable()->index();
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
        Schema::dropIfExists('order_histories');
    }
}
