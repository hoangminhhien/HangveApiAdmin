<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldSkuIdOrderBuyderSubsTables extends Migration
{
    public function up()
    {
        Schema::table('order_buyder_subs', function (Blueprint $table) {
            $table->string('sku_id')->nullable()
                ->default(0)
                ->after('mp_sku_id')
                ->comment('Thuộc tính gốc');
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
                'sku_id'
            ]);
        });
    }
}
