<?php

namespace ThuongDo\Buyder\src;

use ThuongDo\Buyder\src\Services\BaseService;

interface BaseFactory
{
    public static function connection(): BaseService;
}
