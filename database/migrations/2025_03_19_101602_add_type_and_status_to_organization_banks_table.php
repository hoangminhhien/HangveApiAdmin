<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeAndStatusToOrganizationBanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('organization_banks', function (Blueprint $table) {
            $table->tinyInteger('type')->after('bank_id')->default(1)->comment('1: dùng cho khách, 2 dùng nội bộ');
            $table->tinyInteger('status')->after('type')->default(1)->comment('1: trạng thái hoạt động');
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
            $table->dropColumn('type');
            $table->dropColumn('status');
        });
    }
}
