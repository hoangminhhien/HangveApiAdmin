<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImageToCustomerWithdrawalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_withdrawal', function (Blueprint $table) {
            $table->string('image')->after('status')->nullable()->comment('Ảnh giao dịch');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_withdrawal', function (Blueprint $table) {
            $table->dropColumn([
                'image',
            ]);
        });
    }
}
