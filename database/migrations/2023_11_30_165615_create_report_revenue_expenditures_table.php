<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportRevenueExpendituresTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_revenue_expenditures', function (Blueprint $table) {
            $table->id();
            $table->string('fund_id')->nullable();
            $table->string('fund_name')->nullable();
            $table->tinyInteger('fund_type')->comment('1: Thu, 2: Chi')->nullable()->index();
            $table->double('fund_price')->default(0);
            $table->integer('week')->comment('Tuần trong năm')->nullable()->index();
            $table->integer('month')->comment('Tháng trong năm')->nullable()->index();
            $table->integer('quarter')->comment('Quý trong năm')->nullable()->index();
            $table->string('year')->comment('Năm')->nullable()->index();
            $table->string('type')->comment('Loại báo cáo')->nullable()->index();
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
        Schema::dropIfExists('report_revenue_expenditures');
    }
}
