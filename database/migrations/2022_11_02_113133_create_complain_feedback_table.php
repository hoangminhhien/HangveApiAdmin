<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComplainFeedbackTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'complain_feedback',
            function (Blueprint $table) {
                $table->increments('id');
                $table->Morphs('cause');
                $table->integer('complain_id');
                $table->string('content', 500);
                $table->string('type', 20)->default('public');
                $table->boolean('is_seen')->default(0)->comment('Doc tin nhan hay chua');
                $table->timestamps();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('complain_feedback');
    }
}
