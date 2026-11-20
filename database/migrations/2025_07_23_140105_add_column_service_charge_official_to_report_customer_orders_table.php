<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnServiceChargeOfficialToReportCustomerOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_customer_orders', function (Blueprint $table) {
            $table->decimal('service_charge_official', 20)->default(0)->comment('Phí chính ngạch')->after('service_charge');
            $table->decimal('service_charge_official_partner', 20)->default(0)->comment('Phí chính ngạch - Đối tác')->after('service_charge_official');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_customer_orders', function (Blueprint $table) {
            $table->dropColumn([
                'service_charge_official',
                'service_charge_official_partner'
            ]);
        });
    }
}
