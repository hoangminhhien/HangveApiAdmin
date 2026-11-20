<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldLogsSmsTable extends Migration
{
    public function up()
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->tinyInteger('log_type')->default(0)->comment('Loại sms')->index();
            $table->string('user_code', 30)->nullable()->index();
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
                'log_type', 'user_code'
            ]);
        });
    }
}
