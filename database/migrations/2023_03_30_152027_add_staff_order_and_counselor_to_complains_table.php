<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStaffOrderAndCounselorToComplainsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('complains', function (Blueprint $table) {
            $table->integer('staff_counselor_id')->after('staff_care_id')->nullable()->comment('Nhân viên tư vấn');
            $table->integer('staff_order_id')->after('staff_counselor_id')->nullable()->comment('Nhân viên đặt hàng');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('complains', function (Blueprint $table) {
            $table->dropColumn([
                'staff_counselor_id',
                'staff_order_id'
            ]);
        });
    }
}
