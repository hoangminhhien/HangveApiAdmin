<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomerIdToOrderDetailHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_detail_histories', function (Blueprint $table) {
            $table->integer('customer_id')->nullable()->after('user_id')->comment('id khác hàng');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_detail_histories', function (Blueprint $table) {
            $table->dropColumn([
                'customer_id',
            ]);
        });
    }
}
