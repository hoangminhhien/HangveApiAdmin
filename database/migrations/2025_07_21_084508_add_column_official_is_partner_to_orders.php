<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnOfficialIsPartnerToOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('official_is_partner')->default(0)->comment('Chính ngạch - Đi bên đối tác');
        });
        Schema::table('order_supplier', function (Blueprint $table) {
            $table->boolean('official_is_partner')->default(0)->comment('Chính ngạch - Đi bên đối tác');
        });
        Schema::table('order_package', function (Blueprint $table) {
            $table->boolean('official_is_partner')->default(0)->comment('Chính ngạch - Đi bên đối tác');
            $table->decimal('official_service_fee')->default(0)->comment('Phí dịch vụ chính ngạch đồng bộ từ báo giá');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('official_is_partner');
        });
        Schema::table('order_supplier', function (Blueprint $table) {
            $table->dropColumn('official_is_partner');
        });
        Schema::table('order_package', function (Blueprint $table) {
            $table->dropColumn([
                'official_is_partner',
                'official_service_fee'
            ]);
        });
    }
}
