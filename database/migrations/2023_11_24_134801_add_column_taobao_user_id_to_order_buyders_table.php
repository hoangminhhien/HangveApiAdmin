<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_buyders', function (Blueprint $table) {
            $table->string('taobao_user_id')
                ->nullable()
                ->after('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_buyders', function (Blueprint $table) {
            $table->dropColumn(['taobao_user_id']);
        });
    }
};
