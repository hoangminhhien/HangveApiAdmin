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
            $table->string('logistic_number')
                ->nullable()
                ->after('classification_zh')
                ->comment('Mã vận đơn, chỉ có sau khi đơn hàng đã được thanh toán, chạy cron cập nhật');
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
                'logistic_number'
            ]);
        });
    }
};
