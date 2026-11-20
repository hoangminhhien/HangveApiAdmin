<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserDongdongToFilePrintsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('file_prints', function (Blueprint $table) {
            $table->unsignedBigInteger('user_dongdong_id')->nullable();
            $table->string('user_dongdong_name')->nullable();
        });
    }

    public function down()
    {
        Schema::table('file_prints', function (Blueprint $table) {
            $table->dropColumn([
                'user_dongdong_id',
                'user_dongdong_name',
            ]);
        });
    }
}
