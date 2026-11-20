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
        Schema::table('order_buyder_subs', function (Blueprint $table) {
            $table->string('classification_zh')
                ->nullable()
                ->after('mp_sku_id')
                ->comment('Giá trị zh nhận được sau khi tạo đơn, call API chi tiết đơn hàng Taobao');
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
                'classification_zh',
            ]);
        });
    }
};
