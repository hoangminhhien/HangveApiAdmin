<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('category_items', function (Blueprint $table) {
            $table->increments('id');

            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('image')->nullable();
            $table->string('parant_id')->nullable();

            $table->integer('last_time_sync')->default(0)->unsigned()->nullable();

            $table->integer('priority')->default(0)->unsigned()->nullable();
            $table->tinyInteger('hot')->default(0);
            $table->tinyInteger('status')->default(1);

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
        Schema::dropIfExists('category_items');
    }
}
