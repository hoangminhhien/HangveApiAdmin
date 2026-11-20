<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeInternationalShippingCostToOrderPackageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->string('type_international_shipping_cost')->after('international_shipping_cost')->nullable(); //  Tiền vận chuyển quốc tế nó đang tính theo cái nào weight hay volumn
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
                'type_international_shipping_cost'
            ]);
        });
    }
}
