<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnTypeOrderToOrderDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->tinyInteger('type_order')->default(0)->comment('Loại đơn');
        });
        Schema::table('cart_details', function (Blueprint $table) {
            $table->tinyInteger('type_order')->default(0)->comment('Loại đơn');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn([
                'type_order'
            ]);
        });
        Schema::table('cart_details', function (Blueprint $table) {
            $table->dropColumn([
                'type_order'
            ]);
        });
    }
}
