<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ecommerce extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'time',
        'time_type'
    ];

    const TIME_TYPE_DAY = 'day';
    const TIME_TYPE_HOUR = 'hour';
    const TIME_TYPE_MINUTE = 'minute';
    const CACHE_KEY_ECOMMERCE = 'ecommerceServiceConfig';
    const LIST_TIME_TYPE = [
        self::TIME_TYPE_DAY => 'Ngày',
        self::TIME_TYPE_HOUR => 'Giờ',
        self::TIME_TYPE_MINUTE => 'Phút'
    ];
}
