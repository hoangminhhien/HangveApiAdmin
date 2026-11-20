<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderBuyderCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_buyder_categories', function (Blueprint $table) {
            $table->id();
            $table->integer('order_buyder_id')->comment('order_buyders.id');
            $table->integer('order_id')->comment('orders.id');
            $table->integer('order_code')->comment('orders.code');
            $table->integer('category_id')->comment('categories.id');
            $table->timestamps();
        });
        Schema::table('order_buyders', function (Blueprint $table) {
            $table->dateTime('package_time')->nullable()->after('receiver')->comment('Thời gian tạo kiện');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_buyder_categories');
        Schema::table('order_buyders', function (Blueprint $table) {
            $table->dropColumn([
                'package_time',
            ]);
        });
    }
}
