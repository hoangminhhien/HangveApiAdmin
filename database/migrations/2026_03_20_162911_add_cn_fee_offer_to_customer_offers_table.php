<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCnFeeOfferToCustomerOffersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_offers', function (Blueprint $table) {
            $table->decimal('cn_trans_weight_hn_fee', 12,2)->default(0);
            $table->decimal('cn_trans_weight_hcm_fee', 12,2)->default(0);
            $table->decimal('cn_trans_volumn_hn_fee', 12,2)->default(0);
            $table->decimal('cn_trans_volumn_hcm_fee', 12,2)->default(0);
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
            $table->dropColumn([
                'cn_trans_weight_hn_fee',
                'cn_trans_weight_hcm_fee',
                'cn_trans_volumn_hn_fee',
                'cn_trans_volumn_hcm_fee',
            ]);
        });
    }
}
