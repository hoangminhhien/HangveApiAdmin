<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKpiTransportsToUsersGroupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users_group', function (Blueprint $table) {
            $table->decimal('kpi_transports', 20)->comment('Mục tiêu phí vận chuyển');
            $table->decimal('real_transports', 20)->comment('doanh số phí vận chuyển thực tế');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users_group', function (Blueprint $table) {
            $table->dropColumn('kpi_transports');
            $table->dropColumn('real_transports');
        });
    }
}
