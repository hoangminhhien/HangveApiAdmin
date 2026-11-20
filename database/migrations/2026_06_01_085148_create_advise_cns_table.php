<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdviseCnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('advise_cns', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('type')->default(1); // Hình thức
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();

            $table->integer('warehouse_id')->default(0); // Chọn kho nào
            $table->string('warehouse_code')->nullable();

            $table->string('item_name')->nullable();
            $table->string('item_color')->nullable();
            $table->string('item_size')->nullable();
            $table->string('item_material')->nullable();
            $table->string('item_info')->nullable();
            $table->string('item_branch')->nullable();

            $table->string('item_quantity')->nullable();
            $table->double('item_kg')->default(0);
            $table->double('item_m3')->default(0);
            $table->double('item_unit_declare')->default(0); // Đơn giá khai báo

            $table->string('hs_code')->nullable();

            $table->double('t_tax_xnk')->default(0);
            $table->double('t_tax_vat')->default(0);
            $table->double('t_tax_ttdb')->default(0);
            $table->double('t_tax_protect')->default(0);
            $table->double('t_tax_nature')->default(0);

            $table->double('taxTTDB')->default(0);
            $table->double('taxNature')->default(0);
            $table->double('taxProtect')->default(0);
            $table->double('taxVAT')->default(0);
            $table->double('taxXNK')->default(0);

            $table->double('feeVCQT')->default(0);
            $table->double('feeService')->default(0);
            $table->double('feeOpen')->default(0);

            $table->double('total_amount')->default(0);

            $table->integer('user_id')->default(0); // Nhân viên xử lý

            $table->tinyInteger('status')->default(0);
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
        Schema::dropIfExists('advise_cns');
    }
}
