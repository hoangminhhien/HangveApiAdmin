<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnVolumnToOrderPackageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->float('m3')->default(0)->after('width')->comment('Lưu thể tích thực khi nhập trực tiếp không qua thông số dài, rộng, cao'); // Cái này dùng lưu khi lấy dài * rộng * cao lại
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
            $table->dropColumn(['m3']);
        });
    }
}
