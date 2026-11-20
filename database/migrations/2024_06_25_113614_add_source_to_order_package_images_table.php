<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSourceToOrderPackageImagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_package_images', function (Blueprint $table) {
            // Xác định xem ảnh này ở nguồn nào (bên HV hay bên kho đẩy sang)
            $table->tinyInteger('source')->default(0)->after('image'); // 0 - HV, 1- Kho DD
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_package_images', function (Blueprint $table) {
            $table->dropColumn([
                'source',
            ]);
        });
    }
}
