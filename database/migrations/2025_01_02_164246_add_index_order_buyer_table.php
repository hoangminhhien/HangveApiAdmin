<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexOrderBuyerTable extends Migration
{
    public function up()
    {
        Schema::table('order_buyder_subs', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('sourceable_id');
            $table->index('order_buyder_id');
            $table->index('order_no');
            $table->index('buyder_sub_id');
            $table->index('sourceable_type');
            $table->index('source');
            $table->index('staff_id');
        });

        Schema::table('order_buyders', function (Blueprint $table) {
            $table->index('buyder_id');
            $table->index('buyder_no');
            $table->index('purchase_order_outer_id');
            $table->index('supplier_status');
            $table->index('order_source');
            $table->index('source');
        });

        Schema::table('assign_support_orders', function (Blueprint $table) {
            $table->index('order_no');
            $table->index('order_id');
            $table->index('order_detail_id');
            $table->index('assigner_id');
            $table->index('assignee_id');
            $table->index('status');
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
            $table->dropIndex(['order_id']);
            $table->dropIndex(['sourceable_id']);
            $table->dropIndex(['order_buyder_id']);
            $table->dropIndex(['order_no']);
            $table->dropIndex(['buyder_sub_id']);
            $table->dropIndex(['sourceable_type']);
            $table->dropIndex(['source']);
            $table->dropIndex(['staff_id']);
        });

        Schema::table('order_buyders', function (Blueprint $table) {
            $table->dropIndex(['buyder_id']);
            $table->dropIndex(['buyder_no']);
            $table->dropIndex(['purchase_order_outer_id']);
            $table->dropIndex(['supplier_status']);
            $table->dropIndex(['order_source']);
            $table->dropIndex(['source']);
        });

        Schema::table('assign_support_orders', function (Blueprint $table) {
            $table->dropIndex(['order_no']);
            $table->dropIndex(['order_id']);
            $table->dropIndex(['order_detail_id']);
            $table->dropIndex(['assigner_id']);
            $table->dropIndex(['assignee_id']);
            $table->dropIndex(['status']);
        });
    }
}
