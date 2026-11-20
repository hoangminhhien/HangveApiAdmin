<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldPurchaseIdOrders extends Migration
{
    public function up()
    {
        Schema::table('order_buyders', function (Blueprint $table) {
            $table->bigInteger('purchase_id')
                ->after('purchase_order_outer_id')
                ->index()
                ->comment('Mã để thanh toán, hủy bên TaoBao')
                ->default(0)
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_buyders', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_id',
            ]);
        });
    }
}
