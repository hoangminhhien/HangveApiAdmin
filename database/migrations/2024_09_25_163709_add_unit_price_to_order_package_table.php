<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUnitPriceToOrderPackageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->double('unit_price')->after('type_international_shipping_cost')->default(0)->nullable(); // Đơn giá khi tính tiền vận chuyển quốc tế, kiểu 5kg là 10K thì đơn giá là 2K
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
                'unit_price',
            ]);
        });
    }
}
