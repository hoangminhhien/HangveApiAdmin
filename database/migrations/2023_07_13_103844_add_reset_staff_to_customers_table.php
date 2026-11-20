<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddResetStaffToCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('reset_staff')->after('staff_order_id')->nullable(); // 'care', 'counselor'
            $table->integer('reset_staff_time')->after('reset_staff')->default(0)->unsigned()->nullable(); // Thời gian mà thực hiện việc chuyển đổi
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'reset_staff',
                'reset_staff_time',
            ]);
        });
    }
}
