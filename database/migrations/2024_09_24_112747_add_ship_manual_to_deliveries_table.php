<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShipManualToDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Các key này dùng khi in phiếu in cho khách
            $table->double('fee_ship_out')->after('is_delivery_cost_paid')->default(0)->nullable(); // Tiền ship ngoài
            $table->double('fee_missing')->after('fee_ship_out')->default(0)->nullable(); // Tiền còn thiếu nhân viên kho tự nhập
            $table->string('note_print')->after('fee_missing')->nullable(); // Ghi chú tự nhập lúc in phiếu in
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
                'fee_ship_out',
                'fee_missing',
                'note_print',
            ]);
        });
    }
}
