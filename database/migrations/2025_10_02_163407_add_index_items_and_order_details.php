<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexItemsAndOrderDetails extends Migration
{
    public function up()
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->index('mp_id');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->index('num_iid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropIndex(['mp_id']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['num_iid']);
        });
    }
}
