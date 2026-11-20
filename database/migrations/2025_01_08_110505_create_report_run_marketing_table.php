<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportRunMarketingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_run_marketing', function (Blueprint $table) {
            $arr_chanel = [
                'gg_system',
                'gg_form',
                'zalo',
                'facebook',
                'fb_form',
            ];

            $table->id();

            $table->integer('user_id')->default(0)->unsigned()->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();

            $table->integer('group_id')->default(0)->unsigned()->nullable();
            $table->string('group_name')->nullable();

            $table->dateTime('date_start')->nullable(); // Thời gian bắt đầu
            $table->dateTime('date_end')->nullable(); // Thời gian kết thúc

            // 'personal' => 'Tiêu dùng',
            // 'business' => 'Kinh doanh',


            foreach ($arr_chanel as $key => $item) {
                $table->integer('no_interaction_' . $item)->default(0)->unsigned()->nullable(); // Không tương tác được
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('no_need_' . $item)->default(0)->unsigned()->nullable(); // Không có nhu cầu
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('no_enter_good_' . $item)->default(0)->unsigned()->nullable(); // Không nhập được
            }


            foreach ($arr_chanel as $key => $item) {
                $table->integer('need_reference_' . $item)->default(0)->unsigned()->nullable(); // Có nhu cầu - Tham khảo
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('need_order_' . $item)->default(0)->unsigned()->nullable(); // Có nhu cầu - Đã lên đơn
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('need_ordered_' . $item)->default(0)->unsigned()->nullable(); // Có nhu cầu - Đã đặt hàng
            }


            // ----
            foreach ($arr_chanel as $key => $item) {
                $table->integer('ordered_personal_' . $item)->default(0)->unsigned()->nullable(); // Đã đặt hàng - Khách tiêu dùng
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('ordered_business_' . $item)->default(0)->unsigned()->nullable(); // Đã đặt hàng - Khách kinh doanh
            }

            // ----
            foreach ($arr_chanel as $key => $item) {
                $table->integer('consignment_personal_' . $item)->default(0)->unsigned()->nullable(); // Kí gửi - Khách tiêu dùng
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('consignment_business_' . $item)->default(0)->unsigned()->nullable(); // Kí gửi - Khách kinh doanh
            }

            // ----
            foreach ($arr_chanel as $key => $item) {
                $table->integer('pay_help_personal_' . $item)->default(0)->unsigned()->nullable(); // Thanh toán hộ - Khách tiêu dùng
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('pay_help_business_' . $item)->default(0)->unsigned()->nullable(); // Thanh toán hộ - Khách kinh doanh
            }


            $table->integer('organization_id')->default(0)->unsigned()->nullable();
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
        Schema::dropIfExists('report_run_marketing');
    }
}
