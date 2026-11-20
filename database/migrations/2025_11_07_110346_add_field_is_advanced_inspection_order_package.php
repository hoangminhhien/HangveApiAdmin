<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldIsAdvancedInspectionOrderPackage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->tinyInteger('is_advanced_inspection')->default(1)->index()
                ->comment('Kiểm hàng nâng cao : 1 không, 2 có');
            $table->decimal('advanced_inspection_cost', 20)->nullable()->comment('Kiểm hàng nâng cao');
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
            $table->dropColumn('is_advanced_inspection');
            $table->dropColumn('advanced_inspection_cost');
        });
    }
}
