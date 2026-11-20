<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeToApiUsagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('api_usages', function (Blueprint $table) {
            $table->tinyInteger('type')->default(1)->comment('1: Loại KH search, 2: Loại nhân viên search');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('api_usages', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
}
