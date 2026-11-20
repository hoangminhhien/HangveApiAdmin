<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderCodeToReportProductsPipelineStatisticsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_products_pipeline_statistics', function (Blueprint $table) {
            $table->string('order_code')->nullable()->after('quantity')->comment('Mã đơn hàng được tạo ra từ search');
            $table->integer('order_id')->nullable()->after('order_code')->comment('id đơn hàng');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('report_products_pipeline_statistics', function (Blueprint $table) {
            $table->dropColumn('order_code');
            $table->dropColumn('order_id');
        });
    }
}
