<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportControlMarketingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_control_marketing', function (Blueprint $table) {
            $table->id();
            $table->integer('total_customer_business_facebook')->comment('KH kinh doanh FB, ZALO')->default(0);
            $table->integer('total_customer_business_google')->comment('KH kinh doanh GG')->default(0);
            $table->integer('total_customer_personal_facebook')->comment('KH tiêu dùng FB, ZALO')->default(0);
            $table->integer('total_customer_personal_google')->comment('KH tiêu dùng GG')->default(0);
            $table->integer('total_customer_texted_not_respond')->comment('Khách hàng nhắn tin không phản hồi FB,ZALO')->default(0);
            $table->integer('total_customer_calling_not_answer_facebook')->comment('Khách hàng gọi không nghe máy FB, ZALO')->default(0);
            $table->integer('total_customer_calling_not_answer_google')->comment('Khách hàng gọi không nghe máy GG')->default(0);
            $table->integer('total_customer_order_follow_google')->comment('KH đang follow lên đơn GG')->default(0);
            $table->integer('total_customer_order_follow_facebook')->comment('KH đang follow lên đơn FB')->default(0);
            $table->integer('total_customer_order_personal_google')->comment('Khách hàng đã lên đơn tiêu dùng GG')->default(0);
            $table->integer('total_customer_order_business_google')->comment('Khách hàng đã lên đơn kinh doanh GG')->default(0);
            $table->integer('total_customer_order_personal_facebook')->comment('Khách hàng đã lên đơn tiêu dùng FB, ZALO')->default(0);
            $table->integer('total_customer_order_business_facebook')->comment('Khách hàng đã lên đơn kinh doanh FB, ZALO')->default(0);
            $table->integer('total_customer_consignment_advise_google')->comment('KH ký gửi và có gán nv tư vấn trong tuần lấy báo cáo GG')->default(0);
            $table->integer('total_customer_consignment_advise_facebook')->comment('KH ký gửi và có gán nv tư vấn trong tuần lấy báo cáo FB, ZALO')->default(0);
            $table->integer('total_customer_need_but_item_not_imported_google')->comment('KH có nhu cầu nhưng mặt hàng không nhập đươc GG')->default(0);
            $table->integer('total_customer_need_but_item_not_imported_facebook')->comment('KH có nhu cầu nhưng mặt hàng không nhập đươc FB, ZALO')->default(0);
            $table->integer('total_customer_not_immediate_need_google')->comment('KH Tham khảo chưa có nhu cầu ngay GG')->default(0);
            $table->integer('total_customer_not_immediate_need_facebook')->comment('KH Tham khảo chưa có nhu cầu ngay FB, ZALO')->default(0);
            $table->integer('total_customer_not_need_google')->comment('KH khong có nhu cầu GG')->default(0);
            $table->integer('total_customer_not_need_facebook')->comment('KH khong có nhu cầu FB, ZALO')->default(0);
            $table->integer('week')->comment('Tuần trong năm')->nullable()->index();
            $table->integer('month')->comment('Tháng trong năm')->nullable()->index();
            $table->string('year')->comment('Năm')->nullable()->index();
            $table->string('type')->comment('Loại báo cáo')->nullable()->index();
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
        Schema::dropIfExists('report_control_marketing');
    }
}
