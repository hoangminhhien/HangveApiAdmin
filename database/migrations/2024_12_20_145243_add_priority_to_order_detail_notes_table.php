<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPriorityToOrderDetailNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_detail_notes', function (Blueprint $table) {
            $table->tinyInteger('priority')->default(0)->after('order_status')->comment('độ ưu tiên: 0 là bt, 1 là cao');
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
            $table->dropColumn('priority');
        });
    }
}
