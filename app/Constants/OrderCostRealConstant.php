<?php


namespace App\Constants;


class OrderCostRealConstant
{
    public const STATUS_WAITING  = 1;
    public const STATUS_APPROVED = 2;
    public const STATUS_DONE = 3;

    public const STATUSES = [
        self::STATUS_WAITING  => 'Chờ xác nhận',
        self::STATUS_APPROVED => 'Đã xác nhận',
        self::STATUS_DONE => 'Hoàn thành',
    ];

    public const STATUSES_COLOR = [
        self::STATUS_APPROVED => ColorConstant::GREEN,
        self::STATUS_WAITING  => ColorConstant::CARROT_ORANGE,
        self::STATUS_DONE  => ColorConstant::GREEN,
    ];

    public static function getStatusKeys(): array
    {
        return array_keys(self::STATUSES);
    }
}
