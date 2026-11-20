<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogWebhooksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('log_webhooks', function (Blueprint $table) {
            $table->id();

            $table->integer('company_id')->unsigned()->default(0)->nullable(); // Của ai
            $table->tinyInteger('direction')->default(0)->nullable(); // Hướng data đi vào hay đi ra 1 - in, 2 - out

            $table->string('log_name')->nullable();
            $table->string('description')->nullable();
            $table->text('body')->nullable();

            $table->string('from_ip', 50)->nullable();
            $table->integer('user_id')->unsigned()->default(0)->nullable(); // Người nào thao tác

            $table->tinyInteger('count_send')->default(0)->nullable();
            $table->dateTime('time_last_send')->nullable();
            $table->tinyInteger('status')->default(0)->nullable();

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
        Schema::dropIfExists('log_webhooks');
    }
}
