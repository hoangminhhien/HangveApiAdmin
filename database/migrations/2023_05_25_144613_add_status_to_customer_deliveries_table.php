<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToCustomerDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_deliveries', function (Blueprint $table) {
            $table->integer('status')->after('is_default')->default(1)->comment("Trạng thái thông tin đơn đặt hàng");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_deliveries', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}
