<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConfigKpiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('config_kpi', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('type')->default(1)->comment('1: Báo giá, 2: Đặt hàng, 3: Khiếu nại');
            $table->date('date_start')->nullable();
            $table->text('config')->nullable();
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
        Schema::dropIfExists('config_kpi');
    }
}
