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
            $table->unsignedInteger('delay_time')
                ->after('supplier_status')
                ->default(\App\Constants\TimeConstant::UPDATE_LOGISTIC_CODE_PERIOD)
                ->comment('Từ khi đặt hàng, sau thời gian này sẽ dispatch job lấy mã vận đơn, tính bằng phút');
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
                'delay_time'
            ]);
        });
    }
};
