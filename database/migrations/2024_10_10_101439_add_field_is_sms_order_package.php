<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldIsSmsOrderPackage extends Migration
{
    public function up()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->tinyInteger('is_sms')
                ->after('customer_delivery_id')
                ->nullable()
                ->index()
                ->default(0)
                ->comment('0 : Chưa gửi sms, 1 : Đã gửi SMS')
            ;
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
                'is_sms',
            ]);
        });
    }
}
