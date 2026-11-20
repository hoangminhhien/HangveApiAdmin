<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeliveryStoresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delivery_stores', function (Blueprint $table) {
            $table->id();
            $table->string('store_id');
            $table->string('warehouse_id');
            $table->string('name');
            $table->string('phone');
            $table->string('address');
            $table->string('ward_code');
            $table->string('district_id');
            $table->string('client_id');
            $table->string('bank_account_id');
            $table->string('type');
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
        Schema::dropIfExists('delivery_stores');
    }
}
