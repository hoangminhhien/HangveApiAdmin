<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldIsAutoUpdateOrderBuyer extends Migration
{
    public function up()
    {
        Schema::table('order_buyders', function (Blueprint $table) {
            $table->tinyInteger('is_auto_update')
                ->after('is_xadal')
                ->index()
                ->comment('Check xem có tự động cập nhật mã kiện hay không')
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
                'is_auto_update',
            ]);
        });
    }
}
