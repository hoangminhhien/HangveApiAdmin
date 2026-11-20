<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexOrderPackageStatusTime extends Migration
{
    public function up()
    {
        Schema::table('order_package_status_times', function (Blueprint $table) {
            $table->index('key');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_package_status_times', function (Blueprint $table) {
            $table->dropIndex(['key']);
            $table->dropIndex(['created_at']);
        });
    }
}
