<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnOfficialInternationalShippingCostToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->integer('unit_price_international_m3')->unsigned()->default(0)->nullable()->after('official_service_fee')->comment('Đơn giá m3 đồng bộ từ báo giá qua');
            $table->integer('unit_price_international_kg')->unsigned()->default(0)->nullable()->after('official_service_fee')->comment('Đơn giá kg đồng bộ từ báo giá qua');
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->dropColumn([
                'unit_price_international_m3',
                'unit_price_international_kg'
            ]);
        });
    }
}
