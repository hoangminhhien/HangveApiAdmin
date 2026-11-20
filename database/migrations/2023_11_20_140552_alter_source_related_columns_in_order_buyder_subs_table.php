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
        Schema::table('order_buyder_subs', function (Blueprint $table) {
            $table->string('sourceable_type')->after('order_detail_id');
            $table->renameColumn('order_detail_id', 'sourceable_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_buyder_subs', function (Blueprint $table) {
            $table->dropColumn(['sourceable_type']);
            $table->renameColumn('sourceable_id', 'order_detail_id');
        });
    }
};
