<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsignmentHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consignment_histories', function (Blueprint $table) {
            $table->id();
            $table->string('old', 900)->nullable();
            $table->string('new', 900)->nullable();
            $table->text('content')->nullable();
            $table->string('status')->nullable()->index();
            $table->string('user_name')->nullable()->index();
            $table->bigInteger('user_id')->index()->nullable();
            $table->bigInteger('consignment_id')->index()->nullable();
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
        Schema::dropIfExists('consignment_histories');
    }
}
