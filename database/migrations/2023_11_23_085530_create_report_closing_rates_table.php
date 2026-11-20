<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportClosingRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_closing_rates', function (Blueprint $table) {
            $table->id();
            $table->integer('total_all_customer_google')->comment('Khách hàng all google')->default(0);
            $table->integer('total_all_customer_facebook')->comment('Khách hàng all facebook')->default(0);

            $table->integer('total_customer_not_interact_google')->comment('Khách hàng không tương tác được google')->default(0);
            $table->integer('total_customer_not_interact_facebook')->comment('Khách hàng không tương tác được facebook')->default(0);

            $table->integer('total_customer_not_need_google')->comment('Khách hàng không có nhu cầu google')->default(0);
            $table->integer('total_customer_not_need_facebook')->comment('Khách hàng không có nhu cầu facebook')->default(0);

            $table->integer('total_customer_not_imported_google')->comment('Khách hàng có nhu cầu nhưng không nhập được hàng google')->default(0);
            $table->integer('total_customer_not_imported_facebook')->comment('Khách hàng có nhu cầu nhưng không nhập được hàng facebook')->default(0);

            $table->integer('total_customer_not_immediately_google')->comment('Khách hàng tham khảo chưa có nhu cầu ngay google')->default(0);
            $table->integer('total_customer_not_immediately_facebook')->comment('Khách hàng tham khảo chưa có nhu cầu ngay facebook')->default(0);

            $table->integer('total_customer_reference_google')->comment('Khách hàng tham khảo google')->default(0);
            $table->integer('total_customer_reference_facebook')->comment('Khách hàng tham khảo facebook')->default(0);

            $table->integer('total_customer_follow_google')->comment('Khách hàng đang follow google')->default(0);
            $table->integer('total_customer_follow_facebook')->comment('Khách hàng đang follow facebook')->default(0);

            $table->integer('total_customer_personal_google')->comment('Khách hàng tiêu dùng google')->default(0);
            $table->integer('total_customer_personal_facebook')->comment('Khách hàng tiêu dùng facebook')->default(0);

            $table->integer('total_customer_business_google')->comment('Khách hàng kinh doanh google')->default(0);
            $table->integer('total_customer_business_facebook')->comment('Khách hàng kinh doanh facebook')->default(0);

            $table->tinyInteger('week')->comment('Tuần trong năm')->nullable()->index();
            $table->tinyInteger('month')->comment('Tháng trong năm')->nullable()->index();
            $table->string('year', 10)->comment('Năm')->nullable()->index();
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
        Schema::dropIfExists('report_closing_rates');
    }
}
