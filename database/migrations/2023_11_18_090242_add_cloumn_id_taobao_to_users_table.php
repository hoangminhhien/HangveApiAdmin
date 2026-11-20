<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCloumnIdTaobaoToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('id_taobao')->nullable()->after('dongdong_id')->comment('Mã nhân viên trên Taobao');
            $table->string('user_name_taobao')->after('id_taobao')->nullable()->comment('Tên nhân viên trên Taobao');
            $table->string('password_taobao')->after('user_name_taobao')->nullable()->comment('Mật khẩu nhân viên trên Taobao');
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
                'id_taobao',
                'user_name_taobao',
                'password_taobao'
            ]);
        });
    }
}
