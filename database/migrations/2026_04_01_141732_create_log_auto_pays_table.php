<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogAutoPaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('log_auto_pays', function (Blueprint $table) {
            $table->id();
            $table->string('id_pay')->nullable();
            $table->string('account_number')->nullable();
            $table->double('amount')->default(0);
            $table->string('customer_code')->nullable();
            $table->string('content')->nullable(); // Nội dung tin nhắn
            $table->dateTime('date_pay')->nullable(); // Thời gian thực hiện giao dịch
            $table->string('from_ip', 50)->nullable();
            $table->tinyInteger('status')->default(0); // Đã hoàn thành hook này không
            $table->dateTime('date_done')->nullable();
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
        Schema::dropIfExists('log_auto_pays');
    }
}
