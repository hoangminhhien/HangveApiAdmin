<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeTypeReceiverToCustomerDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_deliveries', function (Blueprint $table) {
            $table->string('receiver')->nullable()->change();
            $table->string('address')->nullable()->change();
            $table->string('phone_number', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_deliveries', function (Blueprint $table) {
            $table->string('receiver')->change();
            $table->string('address')->change();
            $table->string('phone_number', 13)->change();
        });
    }
}
