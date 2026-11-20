<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexSyncThuongdoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->index('thuongdo_uuid');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('thuongdo_id');
        });

        Schema::table('customer_deliveries', function (Blueprint $table) {
            $table->index('thuongdo_id');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->index('thuongdo_id');
            $table->index('customer_id');
        });

        Schema::table('order_package', function (Blueprint $table) {
            $table->index('thuongdo_id');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->index('thuongdo_id');
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->index('thuongdo_id');
        });

        Schema::table('order_package_status_times', function (Blueprint $table) {
            $table->index('thuongdo_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('thuongdo_uuid');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->index('thuongdo_id');
        });

        Schema::table('complains', function (Blueprint $table) {
            $table->index('thuongdo_id');
        });

        Schema::table('complain_details', function (Blueprint $table) {
            $table->index('thuongdo_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_uuid']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_id']);
        });

        Schema::table('customer_deliveries', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_id']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_id']);
            $table->dropIndex(['customer_id']);
        });

        Schema::table('order_package', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_id']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_id']);
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_id']);
        });

        Schema::table('order_package_status_times', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_uuid']);
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_id']);
        });

        Schema::table('complains', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_id']);
        });

        Schema::table('complain_details', function (Blueprint $table) {
            $table->dropIndex(['thuongdo_id']);
        });
    }
}
