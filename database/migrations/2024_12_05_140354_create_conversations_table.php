<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            $table->integer('organization_id')->default(0)->unsigned()->nullable();

            $table->tinyInteger('type')->default(0); // Loại gì ? Đơn hàng, kiện này hay khiếu nại

            $table->integer('type_id')->default(0)->unsigned()->nullable();
            $table->string('type_code')->nullable();
            $table->string('type_status')->nullable();

            $table->integer('order_id')->default(0)->unsigned()->nullable();

            $table->integer('customer_id')->default(0)->unsigned()->nullable(); // Của khách hàng nào
            $table->string('customer_code')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_image')->nullable();

            $table->integer('supplier_id')->default(0)->unsigned()->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_image')->nullable();

            $table->integer('user_id')->default(0)->unsigned()->nullable(); // Nhân viên nào care
            $table->string('user_code')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_image')->nullable();

            $table->integer('unread_client')->default(0)->unsigned()->nullable();
            $table->integer('unread_admin')->default(0)->unsigned()->nullable();
            $table->dateTime('time_last_send')->nullable();
            $table->dateTime('time_last_send_client')->nullable();
            $table->dateTime('time_last_send_admin')->nullable();
            $table->string('content_last_send')->nullable();

            $table->integer('conversation_config_id')->default(0)->unsigned()->nullable(); // Trạng thái

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
        Schema::dropIfExists('conversations');
    }
}
