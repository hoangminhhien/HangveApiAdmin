<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldStartDateEndDateReportCustomerVipOrder extends Migration
{
    public function up()
    {
        Schema::table('report_customer_vip_orders', function (Blueprint $table) {
            $table->dateTime('start_date')
                ->nullable()
                ->comment('Ngày bắt đầu là thứ 7 tuần này')
                ->index();
            $table->dateTime('end_date')
                ->nullable()
                ->comment('Ngày kết thúc là thứ 6 tuần tiếp theo')
                ->index();
            $table->bigInteger('total_customer_order')->nullable()->default(0);
            $table->integer('staff_group_ids')->nullable()->default(0)->index();
            $table->bigInteger('total_customer_ordered')->nullable()->default(0);
            $table->bigInteger('total_customer_order_new')->nullable()->default(0);
            $table->bigInteger('total_customer_ordered_new')->nullable()->default(0);
            $table->bigInteger('total_order')->nullable()->default(0);
            $table->bigInteger('total_order_new')->nullable()->default(0);
            $table->decimal('total_price', 20)->nullable()->default(0);
            $table->decimal('total_price_new', 20)->nullable()->default(0);
        });
        /*Schema::table('report_customer_vip_orders', function (Blueprint $table) {
            $table->dropColumn([
                'revenue',
                'total_customer',
                'total_customer_has_order',
                'week',
                'month',
                'year',
                'total_order_all',
                'total_order_order',
            ]);
        });*/
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_customer_vip_orders', function (Blueprint $table) {
            $table->dropColumn([
                'start_date', 'end_date',
                'total_customer_order', 'total_customer_ordered',
                'total_customer_order_new', 'total_customer_ordered_new',
                'total_order', 'total_order_new',
                'total_price', 'total_price_new', 'staff_group_ids'
            ]);
        });
    }
}
