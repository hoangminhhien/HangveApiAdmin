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
        Schema::create('config_shipping_estimated_fee', function (Blueprint $table) {
            $table->id();
            $table->string('category_code')->comment('Mã danh mục, tự generate');
            $table->string('category_id')->comment('Id danh mục, lấy từ bảng categories');
            $table->string('category_name')->comment('Tên danh mục');
            $table->string('weight')->nullable()->comment('Trọng lượng tạm tính');
            $table->string('measurement_unit')->nullable()->comment('Đơn vị đo lường, ví dụ kg, m3');
            $table->string('count_unit')->nullable()->comment('Đơn vị tính, ví dụ cái, hộp');
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
        Schema::dropIfExists('config_shipping_estimated_fee');
    }
};
