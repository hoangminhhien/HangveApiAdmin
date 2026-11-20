<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerOrderSaleStaffTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_order_sale_staff', function (Blueprint $table) {
            $table->id();

            $table->decimal('total_second_customer_amount', 20)->comment('Tổng giá trị đơn hàng từ đơn thứ 2')->default(0);
            $table->decimal('total_second_customer_service_change', 20)->comment('Tổng phí dịch vụ đơn hàng từ đơn thứ 2')->default(0);
            $table->integer('total_second_customer_google')->comment('Khách hàng từ đơn thứ 2 google')->default(0);
            $table->integer('total_second_customer_facebook')->comment('Khách hàng từ đơn thứ 2 facebook')->default(0);

            $table->decimal('total_amount_all', 20)->comment('Tổng giá trị tất cả đơn hàng')->default(0);
            $table->decimal('total_service_change_all', 20)->comment('Tổng phí dịch vụ tất cả đơn hàng')->default(0);

            $table->decimal('total_first_amount_google_personal', 20)->comment('Giá trị đơn hàng đầu tiên của google personal')->default(0);
            $table->decimal('total_first_service_change_google_personal', 20)->comment('Phí dịch vụ đơn hàng đầu tiên của google personal')->default(0);
            $table->integer('total_first_customer_google_personal')->comment('Số lượng khách đơn hàng đầu tiên của google personal')->default(0);

            $table->decimal('total_first_amount_google_ads_personal', 20)->comment('Giá trị đơn hàng đầu tiên của google ads personal')->default(0);
            $table->decimal('total_first_service_change_google_ads_personal', 20)->comment('Phí dịch vụ đơn hàng đầu tiên của google ads personal')->default(0);
            $table->integer('total_first_customer_google_ads_personal')->comment('Số lượng khách đơn hàng đầu tiên của google ads personal')->default(0);

            $table->decimal('total_first_amount_facebook_ads_personal', 20)->comment('Giá trị đơn hàng đầu tiên của facebook ads personal')->default(0);
            $table->decimal('total_first_service_change_facebook_ads_personal', 20)->comment('Phí dịch vụ đơn hàng đầu tiên của facebook ads personal')->default(0);
            $table->integer('total_first_customer_facebook_ads_personal')->comment('Số lượng khách đơn hàng đầu tiên của facebook ads personal')->default(0);

            $table->decimal('total_first_amount_google_business', 20)->comment('Giá trị đơn hàng đầu tiên của google business')->default(0);
            $table->decimal('total_first_service_change_google_business', 20)->comment('Phí dịch vụ đơn hàng đầu tiên của google business')->default(0);
            $table->integer('total_first_customer_google_business')->comment('Số lượng khách đơn hàng đầu tiên của google business')->default(0);

            $table->decimal('total_first_amount_google_ads_business', 20)->comment('Giá trị đơn hàng đầu tiên của google ads business')->default(0);
            $table->decimal('total_first_service_change_google_ads_business', 20)->comment('Phí dịch vụ đơn hàng đầu tiên của google ads business')->default(0);
            $table->integer('total_first_customer_google_ads_business')->comment('Số lượng khách đơn hàng đầu tiên của google ads business')->default(0);

            $table->decimal('total_first_amount_facebook_ads_business', 20)->comment('Giá trị đơn hàng đầu tiên của facebook ads business')->default(0);
            $table->decimal('total_first_service_change_facebook_ads_business', 20)->comment('Phí dịch vụ đơn hàng đầu tiên của facebook ads business')->default(0);
            $table->integer('total_first_customer_facebook_ads_business')->comment('Số lượng khách đơn hàng đầu tiên của facebook ads business')->default(0);

            $table->tinyInteger('week')->comment('Tuần trong năm')->nullable()->index();
            $table->tinyInteger('month')->comment('Tháng trong năm')->nullable()->index();
            $table->string('year', 10)->comment('Năm')->nullable()->index();
            $table->string('user_id')->comment('Id nhân viên tư vấn')->nullable()->index();
            $table->string('user_name')->comment('Nhân viên tư vấn')->nullable()->index();
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
        Schema::dropIfExists('customer_order_sale_staff');
    }
}
