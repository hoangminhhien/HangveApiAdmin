<?php


namespace App\Constants;

class ConversationConstant
{
    public const TYPE_ORDER    = 1;
    public const TYPE_PACKAGE  = 2;
    public const TYPE_COMPLAIN = 3;
    public const TYPE_FREE  = 4; // hội thoại vs kh mình quản lý ko liên quan đến đơn kiện hàng


    public const ARR_TYPE = [
        self::TYPE_ORDER    => 'Đơn hàng',
        self::TYPE_PACKAGE  => 'Kiện hàng',
        self::TYPE_COMPLAIN => 'Khiếu nại',
    ];
}
