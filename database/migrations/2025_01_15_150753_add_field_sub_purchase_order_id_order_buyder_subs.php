<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldSubPurchaseOrderIdOrderBuyderSubs extends Migration
{
    public function up()
    {
        Schema::table('order_buyder_subs', function (Blueprint $table) {
            $table->bigInteger('sub_purchase_order_id')
                ->after('buyder_sub_id')
                ->index()
                ->comment('Mã để tra cứu logistic')
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
        Schema::table('order_buyder_subs', function (Blueprint $table) {
            $table->dropColumn([
                'sub_purchase_order_id',
            ]);
        });
    }
}
