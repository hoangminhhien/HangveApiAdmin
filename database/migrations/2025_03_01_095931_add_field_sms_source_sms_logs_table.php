<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldSmsSourceSmsLogsTable extends Migration
{
    public function up()
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->string('sms_source', 30)->nullable()->index();
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
            $table->dropColumn([
                'sms_source'
            ]);
        });
    }
}
