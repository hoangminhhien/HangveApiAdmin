<?php


namespace App\Constants;

class CustomerPointTime
{
    public const CUSTOMER_POINT_WAY   = 1;
    public const CUSTOMER_POINT_LEVEL = 2;
    public const CUSTOMER_POINT_CARE  = 3;

    public const CUSTOMER_POINT_WAY_MAKE_ACCOUNT = 1001;
    public const CUSTOMER_POINT_WAY_ADD_CART     = 1002;
    public const CUSTOMER_POINT_WAY_UP_ORDER     = 1003;
    public const CUSTOMER_POINT_WAY_MAKE_DEPOSIT = 1004;
    public const CUSTOMER_POINT_WAY_MAKE_ORDERED = 1005;

    public const CUSTOMER_POINT_WAY_TEXT = [
        self::CUSTOMER_POINT_WAY_MAKE_ACCOUNT => 'Đặt hàng',
        self::CUSTOMER_POINT_WAY_ADD_CART     => 'Cho giỏ hàng',
        self::CUSTOMER_POINT_WAY_UP_ORDER     => 'Lên đơn',
        self::CUSTOMER_POINT_WAY_MAKE_DEPOSIT => 'Chờ đặt cọc',
        self::CUSTOMER_POINT_WAY_MAKE_ORDERED => 'Đặt hàng'
    ];
}
