<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrdersNumberIncrementToReportCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_customers', function (Blueprint $table) {
            $table->integer('orders_number_increment')->after('orders_number')->default(0)->unsigned()->nullable(); // Số  đơn của khách tự tăng (key này sẽ ko reset)
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_customers', function (Blueprint $table) {
            $table->dropColumn([
                'orders_number_increment',
            ]);
        });
    }
}
