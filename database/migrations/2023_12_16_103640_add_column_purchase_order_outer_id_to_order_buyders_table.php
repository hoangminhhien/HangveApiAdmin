<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_buyders', function (Blueprint $table) {
            $table->string('purchase_order_outer_id')
                ->nullable()
                ->after('buyder_no')
                ->comment('Mã đặt hàng mới nhận được sau khi tạo đơn, call API chi tiết đơn hàng Taobao');
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
                'purchase_order_outer_id'
            ]);
        });
    }
};
