
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThuongdoIdToDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('thuongdo_id')->after('staff_counselor_id')->nullable();
            $table->double('thuongdo_total_money')->unsigned()->default(0)->nullable(); // Tổng tiền bên TD
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'thuongdo_id',
                'thuongdo_total_money',
            ]);
        });
    }
}
