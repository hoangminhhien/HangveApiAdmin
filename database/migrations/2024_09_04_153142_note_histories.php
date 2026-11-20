<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NoteHistories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('note_histories', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('subject', 'subject');
            $table->integer('user_id')->comment('người sửa');
            $table->string('content')->comment('nội dung');
            $table->integer('organization_id')->comment('công ty');
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
        Schema::dropIfExists('note_histories');
    }
}
