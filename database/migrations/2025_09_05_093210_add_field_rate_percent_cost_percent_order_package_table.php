<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldRatePercentCostPercentOrderPackageTable extends Migration
{
    public function up()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->decimal('cost_percent', 20)->nullable()
                ->default(0)
                ->comment('Số tiền đặt cọc');
            $table->integer('rate_percent')->nullable()
                ->default(0)
                ->comment('% Đặt cọc');
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
                'cost_percent', 'rate_percent'
            ]);
        });
    }
}
