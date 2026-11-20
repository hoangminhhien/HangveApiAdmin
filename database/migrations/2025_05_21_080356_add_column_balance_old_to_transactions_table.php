<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnBalanceOldToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('balance_old', 20)->default(0)->after('thuongdo_id')->comment('Số dư ví khách tại thời điểm 20p trước khi có giao dịch nạp tiền');
            $table->integer('id_check')->default(0)->after('balance_old')->comment('Số dư ví khách tại thời điểm 20p trước khi có giao dịch nạp tiền')->comment('Check tại thời điểm trả hàng có giao dịch nạp tiền nào không');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['balance_old', 'id_check']);
        });
    }
}
