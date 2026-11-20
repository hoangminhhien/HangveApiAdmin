<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexOrderNote extends Migration
{
    public function up()
    {
        Schema::table('order_notes', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('supplier_id');
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_notes', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['supplier_id']);
            $table->dropIndex(['is_public']);
        });
    }
}
