<?php

use App\Constants\PackageConstant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderPackageCancelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_package_cancels', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_package_id')->nullable()->comment('Kiện hàng');
            $table->string('bill_code')->nullable()->comment('Mã vận đơn');
            $table->string('customer_id')->nullable()->comment('id khách hàng');
            $table->string('staff_id', 500)->nullable()->comment('Tên người tạo');
            $table->string('type_cancel_value')->comment('Lý do hủy kiện');
            $table->integer('staff_management_id')->nullable()->comment('Nhân quản lý');
            $table->string('image')->nullable()->comment('Ảnh lý do');
            $table->string('status', 20)->default(PackageConstant::STATUS_CANCEL_PENDING)->comment(
                'Trạng thái'
            );
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
        Schema::dropIfExists('order_package_cancels');
    }
}
