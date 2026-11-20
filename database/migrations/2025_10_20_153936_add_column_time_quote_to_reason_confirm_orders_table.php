<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnTimeQuoteToReasonConfirmOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('reason_confirm_orders', function (Blueprint $table) {
            $table->float('time_quote')->nullable()->after('time')->comment('Thời gian chuyển về chờ báo giá');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('reason_confirm_orders', function (Blueprint $table) {
            $table->dropColumn([
                'time_quote'
            ]);
        });
    }
}
