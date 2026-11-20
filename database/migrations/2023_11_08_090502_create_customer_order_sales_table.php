<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerOrderSalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_order_sales', function (Blueprint $table) {
            $table->id();
            /* (KH ký gửi và có gán nv tư vấn trong tuần lấy báo cáo trên admin + loại KH ( tư vấn kí gửi, KH thanh toán hộ) trên chatbiz )*/
            $table->integer('total_customer_consignment_advise')->comment('KH ký gửi và có gán nv tư vấn trong tuần lấy báo cáo')->default(0);
            $table->integer('total_customer_order_advise')->comment('KH order và có gán nv tư vấn trong tuần lấy báo cáo')->default(0);
            $table->integer('total_customer_consignment')->comment('Khách hàng lên đơn ký gửi trong tuần lấy báo cáo')->default(0);

            $table->integer('total_customer_order_first')->comment('Khách hàng có đơn đầu tiên (trạng thái Đặt hàng) trong tuần báo cáo')->default(0);
            $table->integer('total_customer_order_first_gg_ads_personal')->comment('Khách hàng tiêu dùng mới đặt hàng từ nguồn quảng cáo GG')->default(0);
            $table->integer('total_customer_order_first_gg_ads_business')->comment('Khách hàng kinh doanh mới đặt hàng từ nguồn quảng cáo GG')->default(0);
            $table->integer('total_customer_order_first_fb_ads_personal')->comment('Khách hàng tiêu dùng mới đặt hàng từ nguồn quảng cáo FB')->default(0);
            $table->integer('total_customer_order_first_fb_ads_business')->comment('Khách hàng kinh doanh mới đặt hàng từ nguồn quảng cáo FB')->default(0);

            $table->decimal('total_amount_first', 20)->default(0)->comment('Giá trị đơn hàng mới là giá trị (số tiền hàng) của đơn hàng số 1');
            $table->decimal('total_amount_first_gg_ads_personal', 20)->comment('Giá trị đơn hàng mới đặt hàng từ nguồn quảng cáo GG đơn hàng số 1')->default(0);
            $table->decimal('total_amount_first_gg_ads_business', 20)->comment('Giá trị đơn hàng mới đặt hàng từ nguồn quảng cáo GG đơn hàng số 1')->default(0);
            $table->decimal('total_amount_first_fb_ads_personal', 20)->comment('Giá trị đơn hàng mới đặt hàng từ nguồn quảng cáo FB đơn hàng số 1')->default(0);
            $table->decimal('total_amount_first_fb_ads_business', 20)->comment('Giá trị đơn hàng mới đặt hàng từ nguồn quảng cáo FB đơn hàng số 1')->default(0);

            $table->decimal('total_service_charge_first', 20)->default(0)->comment('Phí dịch vụ đơn hàng mới là giá trị (số tiền hàng) của đơn hàng số 1');
            $table->decimal('total_service_charge_first_gg_ads_personal', 20)->comment('Phí dịch vụ đơn hàng mới đặt hàng từ nguồn quảng cáo GG đơn hàng số 1')->default(0);
            $table->decimal('total_service_charge_first_gg_ads_business', 20)->comment('Phí dịch vụ đơn hàng mới đặt hàng từ nguồn quảng cáo GG đơn hàng số 1')->default(0);
            $table->decimal('total_service_charge_first_fb_ads_personal', 20)->comment('Phí dịch vụ đơn hàng mới đặt hàng từ nguồn quảng cáo FB đơn hàng số 1')->default(0);
            $table->decimal('total_service_charge_first_fb_ads_business', 20)->comment('Phí dịch vụ đơn hàng mới đặt hàng từ nguồn quảng cáo FB đơn hàng số 1')->default(0);

            $table->integer('total_customer_order_second_gg_ads')->comment('Khách hàng có đơn thứ 2 trở đi(trạng thái Đặt hàng) trong tuần báo cáo GG')->default(0);
            $table->integer('total_customer_order_second_fb_ads')->comment('Khách hàng có đơn thứ 2 trở đi (trạng thái Đặt hàng) trong tuần báo cáo FB')->default(0);
            $table->decimal('total_amount_second')->comment('Giá trị đơn thứ 2 trở đi (trạng thái Đặt hàng) trong tuần báo cáo')->default(0);
            $table->decimal('total_service_charge_second')->comment('Phí dịch vụ đơn thứ 2 trở đi (trạng thái Đặt hàng) trong tuần báo cáo')->default(0);

            $table->integer('week')->comment('Tuần trong năm')->nullable()->index();
            $table->integer('month')->comment('Tháng trong năm')->nullable()->index();
            $table->string('year')->comment('Năm')->nullable()->index();
            $table->string('type')->comment('Loại')->nullable()->index();
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
        Schema::dropIfExists('customer_order_sales');
    }
}
