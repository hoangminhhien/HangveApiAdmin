<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldXadalLoginUserTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('xadal_user_code')
                ->after('id_1688')
                ->nullable();
            $table->string('xadal_user_password')
                ->after('id_1688')
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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'xadal_user_code',
            ]);
            $table->dropColumn([
                'xadal_user_password',
            ]);
        });
    }
}
