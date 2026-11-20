<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeToCustomerNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_notes', function (Blueprint $table) {
            $table->tinyInteger('type')->default(0)->after('thuongdo_id')->nullable();;
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_notes', function (Blueprint $table) {
            $table->dropColumn([
                'type',
            ]);
        });
    }
}
