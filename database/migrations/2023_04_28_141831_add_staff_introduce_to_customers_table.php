<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStaffIntroduceToCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('customer_introduce_id')->after('label_id')->nullable()->comment('Người giới thiệu');
            $table->integer('customer_introduce_time_add')->after('customer_introduce_id')->default(0)->unsigned()->nullable()->comment('Thời gian add');
            $table->double('discount_by_kg')->after('customer_introduce_time_add')->unsigned()->default(0)->nullable();
            $table->double('discount_by_m3')->after('discount_by_kg')->unsigned()->default(0)->nullable();
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
                'customer_introduce_id',
                'customer_introduce_time_add',
                'discount_by_kg',
                'discount_by_m3'
            ]);
        });
    }
}
