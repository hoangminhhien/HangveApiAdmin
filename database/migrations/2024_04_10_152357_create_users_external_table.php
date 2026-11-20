<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersExternalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users_external', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->comment('id nhân viên');
            $table->tinyInteger('source')->default(1)->comment('nguồn: 1-xadal');
            $table->string('user_name')->comment('tên đăng nhập');
            $table->string('user_password')->comment('mật khẩu đăng nhập');
            $table->boolean('is_active')->default(true)->comment('trạng thái tài khoản');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users_external');
    }
}
