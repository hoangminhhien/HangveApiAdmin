<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourceToAdviseCnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('advise_cns', function (Blueprint $table) {
            $table->tinyInteger('source')->nullable()->after('status')->comment('1: HV, 2: TD, 3: NH');; // Nguồn tạo
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('advise_cns', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
}
