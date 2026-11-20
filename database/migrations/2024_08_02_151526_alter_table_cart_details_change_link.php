<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableCartDetailsChangeLink extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cart_details', function (Blueprint $table) {
            $table->text('link')->change();
        });
        Schema::table('order_details', function (Blueprint $table) {
            $table->text('link')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cart_details', function (Blueprint $table) {
            $table->string('link')->change();
        });
        Schema::table('order_details', function (Blueprint $table) {
            $table->string('link')->change();
        });
    }
}
