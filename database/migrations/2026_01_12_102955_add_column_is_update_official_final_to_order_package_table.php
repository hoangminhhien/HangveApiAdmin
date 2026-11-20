<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIsUpdateOfficialFinalToOrderPackageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->tinyInteger('is_update_offical_final')->default(0)->comment('Đánh dấu được phép cập nhật phí chính ngạch');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->tinyInteger('is_update_offical_final')->default(0)->comment('Đánh dấu được phép cập nhật phí chính ngạch');
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
            $table->dropColumn('is_update_offical_final');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_update_offical_final');
        });
    }
}
