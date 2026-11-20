<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldOrderDetailClassicZhMuSkuIdZh extends Migration
{
    public function up()
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->string('mp_sku_id_fast')->nullable()->index()
                ->comment('Thuộc tính báo giá chọn');
            $table->string('classification_zh_fast')->nullable()
                ->comment('Thuộc tính báo giá chọn');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn('mp_sku_id_fast');
            $table->dropColumn('classification_zh_fast');
        });
    }
}
