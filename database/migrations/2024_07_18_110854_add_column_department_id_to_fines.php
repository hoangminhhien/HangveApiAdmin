<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnDepartmentIdToFines extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fines', function (Blueprint $table) {
            $table->integer('department_id')->after('id')->default(0)->comment('Phòng ban');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fines', function (Blueprint $table) {
            $table->dropColumn('department_id');
        });
    }
}
