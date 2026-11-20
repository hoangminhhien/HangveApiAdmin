<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerConfirmClosesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_confirm_closes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('customer_id')->index()->nullable();
            $table->bigInteger('user_create_id')->index()->nullable()->comment('Người yêu cầu');
            $table->bigInteger('user_confirm_id')->index()->nullable()->comment('Người duyệt');
            $table->tinyInteger('note_id')->nullable()->index();
            $table->tinyInteger('status')
                ->index()
                ->default(1)
                ->nullable()
                ->comment('1 : Chờ duyệt, 2 : đã duyệt, 3 : Đã hủy');
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
        Schema::dropIfExists('customer_confirm_closes');
    }
}
