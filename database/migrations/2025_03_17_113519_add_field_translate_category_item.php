<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldTranslateCategoryItem extends Migration
{
    public function up()
    {
        Schema::table('category_items', function (Blueprint $table) {
            $table->string('name_cn')
                ->after('name')
                ->nullable()
                ->index();
            $table->string('keyword_type')
                ->after('name')
                ->default('keyword')
                ->nullable()
                ->index();
            $table->bigInteger('user_id')
                ->after('name')
                ->nullable()
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('category_items', function (Blueprint $table) {
            $table->dropColumn([
                'name_cn', 'keyword_type', 'user_id'
            ]);
        });
    }
}
