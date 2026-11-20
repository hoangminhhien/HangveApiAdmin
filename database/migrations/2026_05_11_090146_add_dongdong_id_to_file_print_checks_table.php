<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDongdongIdToFilePrintChecksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('file_print_checks', function (Blueprint $table) {
            $table->integer('dongdong_id')->default(0)->after('order_package_id')->comment('user_id bên kho dongdong');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('file_print_checks', function (Blueprint $table) {
            $table->dropColumn('dongdong_id');
        });
    }
}
