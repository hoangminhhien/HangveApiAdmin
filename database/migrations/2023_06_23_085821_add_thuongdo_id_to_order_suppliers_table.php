<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoIdToOrderSuppliersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_supplier', function (Blueprint $table) {
            $table->string('thuongdo_id')->after('delivery_type')->nullable();
            $table->string('thuongdo_sync')->after('thuongdo_id')->nullable(); // Được sinh ra khi đồng bộ order_id nào ben thuongdo
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_supplier', function (Blueprint $table) {
            $table->dropColumn([
                'thuongdo_id',
                'thuongdo_sync',
            ]);
        });
    }
}
