<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChargeAppHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('charge_app_histories', function (Blueprint $table) {
            $table->id();
            $table->string('charge_content')->comment('cú pháp nạp');
            $table->string('code')->nullable()->comment('mã giao dịch');
            $table->string('content')->comment('nội dung tin nhắn');
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
        Schema::dropIfExists('charge_app_histories');
    }
}
