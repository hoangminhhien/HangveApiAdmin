<?php


namespace App\Constants;


class RefundConstant
{

    public const KEY_STATUS_PENDING = "status_1";
    public const KEY_STATUS_PROCESS = "status_2";
    public const KEY_STATUS_DONE = "status_3";
    public const KEY_STATUS_CANCEL = "status_4";
    public const STATUSES = [
        self::KEY_STATUS_PENDING => 'Chờ xử lý',
        self::KEY_STATUS_PROCESS => 'Đang xử lý',
        self::KEY_STATUS_DONE => 'Đã hoàn thành',
        self::KEY_STATUS_CANCEL => 'Đã hủy'
    ];
    public const STATUSES_COLOR = [
        '0'                      => null,
        self::KEY_STATUS_PENDING => ColorConstant::RED,
        self::KEY_STATUS_PROCESS => ColorConstant::CARROT_ORANGE,
        self::KEY_STATUS_DONE => ColorConstant::GREEN,
        self::KEY_STATUS_CANCEL => ColorConstant::PRUSSIAN_BLUE
    ];

    public const KEY_TYPE_CUSTOMER = 1;
    public const KEY_TYPE_PAYMENT = 2;

    public const TYPES = [
        self::KEY_TYPE_CUSTOMER => 'Hoàn tiền cho khách',
        self::KEY_TYPE_PAYMENT => 'Thêm thanh toán cho khách',
    ];
}
