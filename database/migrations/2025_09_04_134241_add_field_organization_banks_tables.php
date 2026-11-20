<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldOrganizationBanksTables extends Migration
{
    public function up()
    {
        Schema::table('organization_banks', function (Blueprint $table) {
            $table->tinyInteger('type_payment')->default(0)->nullable()
                ->comment('1 : Ca nhan, 2 : Doanh nghiep');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('organization_banks', function (Blueprint $table) {
            $table->dropColumn([
                'type_payment'
            ]);
        });
    }
}
