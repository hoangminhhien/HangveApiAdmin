<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFundLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fund_log', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('type')->comment('1: Update, 2: Delete');
            $table->integer('fund_id')->comment('id quỹ');
            $table->string('fund_name')->comment('tên quỹ');
            $table->text('data');
            $table->integer('user_id')->comment('id người thực hiện');
            $table->string('user_name')->comment('tên người thực hiện');
            $table->integer('organization_id')->comment('id quỹ');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fund_log');
    }
}
