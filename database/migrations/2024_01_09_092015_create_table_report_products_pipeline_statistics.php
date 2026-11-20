<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_products_pipeline_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30)->comment('Nguồn thực hiện hành động (Form nhúng,
                Web PC, Web Mobile...), định nghĩa trong ClientApi/platform/Report ProductsPipelineConstant.php');
            $table->string('action', 30)->comment('Hành động (Tìm kiếm, thêm vào giỏ hàng,
                tạo đơn, đặt hàng...), định nghĩa trong ClientApi/platform/Report ProductsPipelineConstant.php');
            $table->string('platform', 30)->comment('Sàn TMĐT nào, định nghĩa trong
                ClientApi/platform/Report ProductsPipelineConstant.php');
            $table->string('keyword')->comment('Từ khóa tìm kiếm');
            $table->string('ip_address', 50)->comment('Địa chỉ IP thực hiện request');
            $table->string('city', 30)->comment('Thành phố nơi thực hiện request');
            $table->unsignedInteger('quantity')->comment('Số lượng request đã thực hiện');
            $table->string('customer_id', 50)
                ->comment('ID khách hàng, customers.id')->nullable();
            $table->string('customer_name', 50)
                ->comment('Tên khách hàng, customers.name')->nullable();
            $table->string('customer_code', 30)
                ->comment('Mã khách hàng, customers.code')->nullable();
            $table->string('customer_phone', 30)
                ->comment('Sđt khách hàng, customers.phone_number')->nullable();
            $table->unsignedTinyInteger('week')->comment('Thứ tự tuần trong tháng (1 -> 5)');
            $table->unsignedTinyInteger('month')->comment('Thứ tự tháng trong năm (1 -> 12)');
            $table->unsignedSmallInteger('year')->comment('Năm');
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
        Schema::dropIfExists('report_products_pipeline_statistics');
    }
};
