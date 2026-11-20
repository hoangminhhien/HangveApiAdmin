<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('report_products_pipeline_statistics', function (Blueprint $table) {
            $table->unsignedTinyInteger('day')
                ->comment('Ngày trong tuần (1 -> 7)')
                ->after('customer_phone');
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
            $table->dropColumn('day');
        });
    }
};
