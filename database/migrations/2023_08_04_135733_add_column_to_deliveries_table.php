<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnToDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->integer('staff_care_id')->nullable()->comment('Nhân viên chăm sóc')->after('status');
            $table->integer('staff_quotation_id')->nullable()->comment('Nhân viên báo giá')->after('staff_care_id');
            $table->integer('staff_order_id')->nullable()->comment('Nhân viên đặt hàng')->after('staff_quotation_id');
            $table->integer('staff_counselor_id')->nullable()->comment('Nhân viên tư vấn')->after('staff_order_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'staff_care_id',
                'staff_quotation_id',
                'staff_order_id',
                'staff_counselor_id'
            ]);
        });
    }
}
