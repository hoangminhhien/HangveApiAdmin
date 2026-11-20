<?php

namespace Database\Migrations\Common;

use Illuminate\Database\Migrations\Migration;

/**
 * Các migrations mới extends BaseMigration này để đỡ phải định nghĩa lại các cột chung
 */
class BaseMigration extends Migration
{
    use CommonColumnsTrait;
}
