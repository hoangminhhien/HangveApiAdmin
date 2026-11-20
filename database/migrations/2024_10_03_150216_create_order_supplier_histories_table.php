<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderSupplierHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_supplier_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id')->default(0)->unsigned()->nullable();
            $table->integer('order_supplier_id')->default(0)->unsigned()->nullable();
            $table->integer('supplier_id')->default(0)->unsigned()->nullable();
            $table->string('object')->nullable();
            $table->string('value')->nullable();
            $table->string('value_old')->nullable();
            $table->string('order_status')->nullable();
            $table->integer('user_id')->default(0)->unsigned()->nullable();
            $table->integer('thuongdo_id')->default(0)->unsigned()->nullable();
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
        Schema::dropIfExists('order_supplier_histories');
    }
}
