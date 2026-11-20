<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMoneyToOrderRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->decimal('money_refund', 20)->default(0)->comment('số tiền VN cần hoàn');
            $table->decimal('money_cny_refund', 20)->default(0)->nullable('số tiền tệ cần hoàn');
            $table->integer('created_user_id')->index()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->dropColumn('money_refund');
            $table->dropColumn('money_cny_refund');
            $table->dropColumn('created_user_id');
        });
    }
}
