<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnToReportCustomerOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_customer_orders', function (Blueprint $table) {
            $table->dateTime('start_date')
                ->nullable()
                ->after('total_order')
                ->comment('Ngày bắt đầu là thứ 7 tuần này')
                ->index();
            $table->dateTime('end_date')
                ->nullable()
                ->after('start_date')
                ->comment('Ngày kết thúc là thứ 6 tuần tiếp theo')
                ->index();
            $table->dropColumn([
                    'week', 'month', 'type', 'year', 'filter_by_customer'
                ]);
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
                'start_date', 'end_date'
            ]);
            $table->integer('week')->comment('Tuần trong năm')->nullable()->index();
            $table->tinyInteger('month')->after('week')->nullable()->index()->comment('Tháng');
            $table->string('year')->comment('Năm')->nullable()->index();
            $table->string('type')->after('month')->nullable()->index()->comment('Loại');
            $table->string('filter_by_customer')->nullable()->comment('old: KH cũ, new: KH mới')->after('total_order');
        });
    }
}
