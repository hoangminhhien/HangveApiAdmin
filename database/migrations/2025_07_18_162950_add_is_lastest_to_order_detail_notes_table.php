<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsLastestToOrderDetailNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_detail_notes', function (Blueprint $table) {
            $table->boolean('is_lastest')->default(0)->after('order_id')->comment('Là note cuối cùng trong đơn của ncc đó');
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
            $table->dropColumn('is_lastest');
        });
    }
}
