<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportCheckMarketingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_check_marketing', function (Blueprint $table) {
            $arr_chanel = [
                'livechat',
                'system',
                'zalo',
                'facebook',
                'gg_form',
                'fb_form',
            ];

            $table->id();
            $table->integer('organization_id')->default(0)->unsigned()->nullable();

            // 'personal' => 'Tiêu dùng',
            // 'business' => 'Kinh doanh',

            foreach ($arr_chanel as $key => $item) {
                $table->integer('no_interaction_' . $item)->default(0)->unsigned()->nullable()->comment('Không tương tác được');
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('no_need_' . $item)->default(0)->unsigned()->nullable()->comment('Không có nhu cầu');
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('need_but_no_enter_good_' . $item)->default(0)->unsigned()->nullable()->comment('Có nhu cầu nhưng không nhập được');
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('reference_but_no_need_now_' . $item)->default(0)->unsigned()->nullable()->comment('Kham khảo chưa có nhu cầu ngay');
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('reference_' . $item)->default(0)->unsigned()->nullable()->comment('Tham khảo');
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('follow_' . $item)->default(0)->unsigned()->nullable()->comment('Follow');
            }
            // ----
            foreach ($arr_chanel as $key => $item) {
                $table->integer('ordered_personal_' . $item)->default(0)->unsigned()->nullable()->comment('Đã đặt hàng - Khách tiêu dùng');
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('ordered_business_' . $item)->default(0)->unsigned()->nullable()->comment('Đã đặt hàng - Khách kinh doanh');
            }
            // ----
            foreach ($arr_chanel as $key => $item) {
                $table->integer('consignment_personal_' . $item)->default(0)->unsigned()->nullable()->comment('Kí gửi - Khách tiêu dùng');
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('consignment_business_' . $item)->default(0)->unsigned()->nullable()->comment('Kí gửi - Khách kinh doanh');
            }
            // ----
            foreach ($arr_chanel as $key => $item) {
                $table->integer('pay_help_personal_' . $item)->default(0)->unsigned()->nullable()->comment('Thanh toán hộ - Khách tiêu dùng');
            }
            foreach ($arr_chanel as $key => $item) {
                $table->integer('pay_help_business_' . $item)->default(0)->unsigned()->nullable()->comment('Thanh toán hộ - Khách kinh doanh');
            }


            $table->dateTime('date_start')->nullable()->comment('Thời gian bắt đầu');
            $table->dateTime('date_end')->nullable()->comment('Thời gian kết thúc');

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
        Schema::dropIfExists('report_check_marketing');
    }
}
