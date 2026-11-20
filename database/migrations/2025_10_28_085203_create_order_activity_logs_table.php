<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderActivityLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->index()->nullable()->default(0);
            $table->string('action')->index()->nullable();
            $table->string('model_type')->index()->nullable();
            $table->bigInteger('model_id')->index()->nullable()->default(0);
            $table->longText('properties')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
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
        Schema::dropIfExists('order_activity_logs');
    }
}
