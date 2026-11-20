<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsCircleOrderAssignStaffToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_circle_order_assign_staff')->default(false)
                ->comment('Dùng để xoay vòng gán nhân viên đặt hàng ở trạng thái chờ báo giá, chờ đặt cọc');
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
            $table->dropColumn('is_circle_order_assign_staff');
        });
    }
}
