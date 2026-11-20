<?php


namespace App\Constants;


class SubOrderBuyderConstant
{
    public const STATUS_BULIDING = 'BULIDING';
    public const STATUS_WAIT_BUYER_P = 'WAIT_BUYER_P';
    public const STATUS_WAIT_SELLER_SEND_GOODS = 'WAIT_SELLER_SEND_GOODS';
    public const STATUS_WAIT_BUYER_CONFIRM_GOODS = 'WAIT_BUYER_CONFIRM_GOODS';
    public const STATUS_TRADE_CLOSED = 'TRADE_CLOSED';

    public const STATUSES = [
        self::STATUS_BULIDING => 'Danh sách mua sắm được tạo ra',
        self::STATUS_WAIT_BUYER_P => 'Chờ thanh toán',
        self::STATUS_WAIT_SELLER_SEND_GOODS => 'Thanh toán, chờ giao hàng',
        self::STATUS_WAIT_BUYER_CONFIRM_GOODS => 'Đã thanh toán, đã được vận chuyển',
        self::STATUS_TRADE_CLOSED => 'Giao dịch đóng cửa',
    ];
}