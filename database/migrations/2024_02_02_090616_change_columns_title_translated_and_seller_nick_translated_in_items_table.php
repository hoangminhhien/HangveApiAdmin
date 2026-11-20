<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->renameColumn('title_translated', 'title_vi')
                ->comment('Tên sản phẩm đã được dịch qua Tiếng Việt');
            $table->renameColumn('seller_nick_translated', 'seller_nick_vi')
                ->comment('Tên nhà cung cấp đã được dịch qua Tiếng Việt');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->renameColumn('title_vi', 'title_translated')
                ->comment('Tên sản phẩm đã được dịch qua Tiếng Việt');
            $table->renameColumn('seller_nick_vi', 'seller_nick_translated')
                ->comment('Tên nhà cung cấp đã được dịch qua Tiếng Việt');
        });
    }
};
