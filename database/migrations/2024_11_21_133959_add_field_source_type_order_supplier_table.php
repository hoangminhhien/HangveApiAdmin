<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldSourceTypeOrderSupplierTable extends Migration
{
    public function up()
    {
        Schema::table('order_supplier', function (Blueprint $table) {
            $table->tinyInteger('source_type')
                ->index()
                ->nullable()
                ->default(0)
                ->after('supplier_id')
                ->comment('1 : nguồn add on, 2 : nguồn APP, 3 : nguồn api web, 4 : nguồn search form');
            $table->index('supplier_id');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->tinyInteger('source_type')
                ->nullable()
                ->after('supplier_id')
                ->comment('1 : nguồn add on, 2 : nguồn APP, 3 : nguồn api web, 4 : nguồn search form');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_supplier', function (Blueprint $table) {
            $table->dropColumn(['source_type']);
            $table->dropIndex(['supplier_id']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn(['source_type']);
        });
    }
}
