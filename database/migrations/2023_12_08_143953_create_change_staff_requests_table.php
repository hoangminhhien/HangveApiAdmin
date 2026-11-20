<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('change_staff_requests', function (Blueprint $table) {
            $table->id();
            $table->string("customer_id")->comment("ID khách hàng");
            $table->text("reason")->comment("Lý do chuyển nhân viên");
            $table->string("staff_name_request")->nullable()->comment("Yêu cầu chuyển đến nhân viên");
            $table->string("staff_group_id_old")->nullable()->comment("ID nhân viên đang hỗ trợ");
            $table->string("staff_group_id")->nullable()->comment("ID nhân viên hỗ trợ mới");
            $table->tinyInteger("status")->default(0)->comment("Trạng thái xử lý");
            $table->string("updated_by")->nullable()->comment("ID người cập nhật gần nhất");
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('change_staff_requests');
    }
};
