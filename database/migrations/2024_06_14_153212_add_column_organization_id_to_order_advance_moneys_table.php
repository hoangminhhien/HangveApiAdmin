<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnOrganizationIdToOrderAdvanceMoneysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_advance_moneys', function (Blueprint $table) {
            $table->integer('organization_id')->after('id')->default(0)->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_advance_moneys', function (Blueprint $table) {
            $table->dropColumn([
                'organization_id',
            ]);
        });
    }
}
