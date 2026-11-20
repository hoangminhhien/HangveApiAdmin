<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPhoneCallOutFullToUserChatbizPingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_chatbiz_pings', function (Blueprint $table) {
            $table->text('phone_call_out_full_list')->after('phone_call_in_list')->nullable(); // Danh sách khách gọi vào full
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_chatbiz_pings', function (Blueprint $table) {
            $table->dropColumn([
                'phone_call_out_full_list'
            ]);
        });
    }
}
