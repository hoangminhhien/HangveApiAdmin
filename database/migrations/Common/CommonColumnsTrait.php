<?php

namespace Database\Migrations\Common;

use Illuminate\Database\Schema\Blueprint;

trait CommonColumnsTrait
{
    /**
     * Định nghĩa các cột sử dụng chung trong nhiều bảng
     *
     * @param Blueprint $table
     * @return void
     */
    public function getCommonColumns(Blueprint $table)
    {
        $table->increments('id');
        // $table->increments('id_number');
        $table->integer('organization_id');
    }
}
