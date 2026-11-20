<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOfficialToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->tinyInteger('official')->default(1)->comment('1. Hàng bán chính ngạch, 2. Hàng chính ngạch');
            $table->tinyInteger('type_official')->default(0)
                ->comment('1: Ủy Thác nhập khẩu, 2: Tự đứng tên tờ khai. Chỉ khi nào chọn chính ngạch mới có');
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
            $table->dropColumn('official');
            $table->dropColumn('type_official');
        });
    }
}
