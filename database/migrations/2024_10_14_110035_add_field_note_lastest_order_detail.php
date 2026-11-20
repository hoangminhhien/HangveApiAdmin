<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldNoteLastestOrderDetail extends Migration
{
    public function up()
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->string('note_staff_latest')
                ->after('is_from_addon')
                ->nullable()
                ->comment('Lưu note mới nhất của khách')
            ;
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
            $table->dropColumn([
                'note_staff_latest',
            ]);
        });
    }
}
