<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeColumnRequestReasonToApproveUnitPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('approve_unit_prices', function (Blueprint $table) {
            $table->string('request_reason', 500)->nullable()->comment('Lý do yêu cầu')->change();
            $table->string('cancel_reason', 500)->nullable()->comment('Lý do hủy yêu cầu')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('approve_unit_prices', function (Blueprint $table) {
            $table->string('request_reason')->nullable()->comment('Lý do yêu cầu')->change();
            $table->string('cancel_reason')->nullable()->comment('Lý do hủy yêu cầu')->change();
        });
    }
}
