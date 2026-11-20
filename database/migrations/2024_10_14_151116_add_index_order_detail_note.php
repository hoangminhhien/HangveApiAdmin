<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexOrderDetailNote extends Migration
{
    public function up()
    {
        Schema::table('order_detail_notes', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('order_detail_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_detail_notes', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'order_detail_id']);
        });
    }
}
