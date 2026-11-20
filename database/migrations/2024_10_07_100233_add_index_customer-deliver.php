<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexCustomerDeliver extends Migration
{
    public function up()
    {
        Schema::table('customer_deliveries', function (Blueprint $table) {
            $table->index('status');
            $table->index('ward_id');
            $table->index('district_id');
            $table->index('province_id');
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
            $table->dropIndex(['status', 'ward_id', 'district_id', 'province_id']);
        });
    }
}
