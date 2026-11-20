<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeColumnsTransInCustomerOffersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_offers', function (Blueprint $table) {
            $table->decimal('trans_weight_hn_fee', 10, 2)->change();
            $table->decimal('trans_weight_hcm_fee', 10, 2)->change();
            $table->decimal('trans_weight_hp_fee', 10, 2)->change();
            $table->decimal('trans_volumn_hn_fee', 10, 2)->change();
            $table->decimal('trans_volumn_hcm_fee', 10, 2)->change();
            $table->decimal('trans_volumn_hp_fee', 10, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_offers', function (Blueprint $table) {
            $table->decimal('trans_weight_hn_fee', 8, 2)->change();
            $table->decimal('trans_weight_hcm_fee', 8, 2)->change();
            $table->decimal('trans_weight_hp_fee', 8, 2)->change();
            $table->decimal('trans_volumn_hn_fee', 8, 2)->change();
            $table->decimal('trans_volumn_hcm_fee', 8, 2)->change();
            $table->decimal('trans_volumn_hp_fee', 8, 2)->change();
        });
    }
}
