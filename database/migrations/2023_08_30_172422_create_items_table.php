<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('items', function (Blueprint $table) {
            $table->increments('id');

            $table->string('key_search')->nullable(); // Từ khóa seach

            $table->string('num_iid')->nullable();
            $table->string('pic')->nullable();
            $table->string('title')->nullable();
            $table->double('price')->unsigned()->default(0)->nullable();
            $table->double('promotion_price')->unsigned()->default(0)->nullable();
            $table->string('sales')->nullable();
            $table->string('seller_id')->nullable();
            $table->string('seller_nick')->nullable();
            $table->string('user_type')->nullable();
            $table->string('delivery_fee')->nullable();
            $table->string('detail_url')->nullable();

            $table->text('data')->nullable(); // Thông tin chi tiết của sản phẩm
            $table->integer('last_time_sync')->default(0)->unsigned()->nullable();

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
        Schema::dropIfExists('items');
    }
}
