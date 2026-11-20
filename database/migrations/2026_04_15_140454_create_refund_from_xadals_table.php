<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRefundFromXadalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('refund_from_xadals', function (Blueprint $table) {
            $table->id();
            $table->string('buyder_no')->nullable()
                ->comment('tương đương với order_code bên xadal');
            $table->string('order_code')->nullable()
                ->comment('tương đương purchase_outer_id đối với đơn taobao, hoặc order_code bên 1688 của xadal');
            $table->decimal('money', 14, 2)->nullable();
            $table->integer('order_id')->default(0)->comment('order id của HV');
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
        Schema::dropIfExists('refund_from_xadals');
    }
}
