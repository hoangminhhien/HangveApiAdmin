<?php

namespace ThuongDo\Buyder\src;

use Illuminate\Support\Facades\Facade;

class Buyder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'BuyderFactory';
    }
}
