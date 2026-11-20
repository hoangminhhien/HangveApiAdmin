<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnOfficialServiceFeeToReportOrderConsignmentDate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_order_consignment_date', function (Blueprint $table) {
            $table->decimal('official_service_fee', 20)->default(0)->after('china_shipping_cost')->comment('Phí dịch vụ chính ngạch');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_order_consignment_date', function (Blueprint $table) {
            $table->dropColumn([
                'official_service_fee'
            ]);
        });
    }
}
