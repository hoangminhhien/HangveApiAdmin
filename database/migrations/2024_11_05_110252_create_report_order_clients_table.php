<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportOrderClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_order_clients', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index()->nullable()->comment('Loại thời gian');
            $table->bigInteger('time')->index()->nullable();
            $table->integer('customer_id')->index()->nullable();
            $table->date('date')->index()->nullable();
            $table->integer('month')->index()->nullable();
            $table->string('year', 10)->index()->nullable();
            $table->bigInteger('total_order_done')->nullable()->default(0)
                ->comment('Tổng đơn hàng hoàn thành');
            $table->bigInteger('total_order_deposited')->nullable()->default(0)
                ->comment('Tổng đơn hàng đặt cọc');
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
        Schema::dropIfExists('report_order_clients');
    }
}
