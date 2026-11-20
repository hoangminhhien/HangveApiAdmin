<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInitTotalDepositToReportCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_customers', function (Blueprint $table) {
            $table->decimal('init_total_deposit', 20)->after('packages_number')->default(0)->nullable(); // Số tiền nạp đồng bộ
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
                'init_total_deposit'
            ]);
        });
    }
}
