<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldFeeBaoGiaOrderPakagesTables extends Migration
{
    public function up()
    {
        Schema::table('order_package', function (Blueprint $table) {
            $table->decimal('special_consumption_tax', 20)->nullable()
                ->default(0)
                ->comment('Thue suat tieu thu dac biet lay tu Bao Gia');

            $table->decimal('tax_xnk', 20)->nullable()
                ->default(0)
                ->comment('Thue suat XNK lay tu Bao Gia');

            $table->decimal('tax_vat', 20)->nullable()
                ->default(0)
                ->comment('Thue suat VAT lay tu Bao Gia');

            $table->decimal('environmental_tax', 20)->nullable()
                ->default(0)
                ->comment('Thue suat moi truong lay tu Bao Gia');

            $table->decimal('safeguard_tax', 20)->nullable()
                ->default(0)
                ->comment('Thue suat tu ve lay tu Bao Gia');

            $table->decimal('surcharge', 20)->nullable()
                ->default(0)
                ->comment('Phu phi lay tu Bao Gia');
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
                'official_service_fee', 'special_consumption_tax', 'tax_xnk',
                'tax_vat', 'environmental_tax', 'safeguard_tax', 'surcharge'
            ]);
        });
    }
}
