<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldResposeStatusSmsLogsTable extends Migration
{
    public function up()
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->integer('response_status')->index()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropColumn(['response_status']);
        });
    }
}
