<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnRevenueOfficialToReportCustomerOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_customer_orders', function (Blueprint $table) {
            $table->decimal('revenue_official', 20)->default(0)->comment('Giá trị đơn hàng chính ngạch')->after('revenue');
            $table->decimal('revenue_official_partner', 20)->default(0)->comment('Giá trị đơn hàng chính ngạch - Đối tác')->after('revenue_official');
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
                'revenue_official',
                'revenue_official_partner'
            ]);
        });
    }
}
