<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupKpiMonthsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group_kpi_months', function (Blueprint $table) {
            $table->id();
            $table->integer('group_id');
            $table->string('month');
            $table->tinyInteger('kpi')->default(1)->comment('1: sales, 2: services');
            $table->decimal('kpi_sales', 20)->comment('Mục tiêu doanh số');
            $table->decimal('kpi_services', 20)->comment('Mục tiêu phí dịch vụ');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_kpi_months');
    }
}
