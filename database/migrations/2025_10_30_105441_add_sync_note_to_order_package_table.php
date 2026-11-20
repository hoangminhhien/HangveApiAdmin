<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSyncNoteToOrderPackageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->tinyInteger('sync_note')->default(1)->after('amount')->nullable(); // Có đồng bộ ghi chú sang kho không
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->dropColumn([
                'sync_note'
            ]);
        });
    }
}
