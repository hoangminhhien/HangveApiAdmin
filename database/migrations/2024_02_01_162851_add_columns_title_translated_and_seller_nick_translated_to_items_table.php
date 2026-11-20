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
            $table->string('title_translated')->nullable()->after('title')
                ->command('Tên sản phẩm đã được dịch qua Tiếng Việt');
            $table->string('seller_nick_translated')->nullable()->after('seller_nick')
                ->command('Tên nhà cung cấp đã được dịch qua Tiếng Việt');
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
            $table->dropColumn([
                'title_translated',
                'seller_nick_translated'
            ]);
        });
    }
};
