<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMakeUpToOrderPackageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->tinyInteger('is_make_up')->default(0)->after('status')->comment('Đánh dấu là kiện phát bù. 1 là phát bù');
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
            $table->dropColumn([
                'is_make_up',
            ]);
        });
    }
}
