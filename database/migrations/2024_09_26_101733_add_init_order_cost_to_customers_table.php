<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInitOrderCostToCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->double('init_order_complete')->after('level')->default(0)->nullable(); // Tiền hàng khách đã mua được đồng bộ từ bên TD
            $table->double('total_order_complete')->after('init_order_complete')->default(0)->nullable(); // Dùng để nâng cấp level
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'init_order_complete',
                'total_order_complete',
            ]);
        });
    }
}
