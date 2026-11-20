<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnAmountImportToDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->decimal('amount_import', 20)->default(0)->after('payment')->comment('Số tiền import ở file đối soát');
            $table->decimal('customer_balance_old', 20)->default(0)->after('amount_import')->comment('Số dư ví khách trước khi thực hiện thanh toán giao hàng');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['amount_import', 'customer_balance_old']);
        });
    }
}
