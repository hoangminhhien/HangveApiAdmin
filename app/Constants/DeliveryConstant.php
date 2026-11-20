<?php


namespace App\Constants;


class DeliveryConstant
{
    public const PAYMENT_E_WALLET = 'e-wallet';
    public const PAYMENTS = [
        'cod'      => 'COD',
        'e-wallet' => 'Ví điện tử'
    ];

    public const KEY_STATUS_PENDING = 'status_0';
    public const KEY_STATUS_PROCESS = 'status_1';
    public const KEY_STATUS_DONE = 'status_2';
    public const KEY_STATUS_CANCEL = 'status_3';
    public const KEY_STATUS_RUN = 'status_4'; // Đang giao hàng
    public const STATUS_DONE_INDEX = 2;

    public const STATUSES = [
        self::KEY_STATUS_PENDING => 'Chờ xử lý',
        self::KEY_STATUS_PROCESS => 'Đang xử lý',
        self::KEY_STATUS_RUN     => 'Đang giao hàng',
        self::KEY_STATUS_DONE    => 'Đã hoàn thành',
        self::KEY_STATUS_CANCEL  => 'Đã hủy'
    ];

    public const STATUSES_COLOR = [
        self::KEY_STATUS_PENDING => ColorConstant::STRAWBERRY,
        self::KEY_STATUS_PROCESS => ColorConstant::CARROT_ORANGE,
        self::KEY_STATUS_RUN     => ColorConstant::PRUSSIAN_BLUE,
        self::KEY_STATUS_DONE    => ColorConstant::NAVY_BLUE,
        self::KEY_STATUS_CANCEL  => ColorConstant::PRUSSIAN_BLUE
    ];

    public const STATUSES_PAYMENT = [
        'undefined' => 'Lựa chọn trạng thái',
        '1'         => 'Đã thanh toán vào ví',
        '0'         => 'Chưa thanh toán hết'
    ];

    public const ORDER_IN_DAY   = 'day';
    public const ORDER_IN_WEEK  = 'week';
    public const ORDER_IN_MONTH = 'month';
}
