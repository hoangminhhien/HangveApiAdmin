<?php


namespace App\Constants;

class AdviseCNConstant
{
    public const TYPE_SELF        = 1; // Tự đứng tên tờ khai
    public const TYPE_ENTRUSTMENT = 2; // Ủy thác nhập khẩu

    public const TYPE_TEXT = [
        self::TYPE_SELF        => 'Tự đứng tên tờ khai',
        self::TYPE_ENTRUSTMENT => 'Ủy thác nhập khẩu',
    ];

    public const STATUS_WAITING = 0;
    public const STATUS_RECEIVED = 1;

    public const STATUSES_TEXT = [
        self::STATUS_WAITING => 'Chờ tiếp nhận',
        self::STATUS_RECEIVED => 'Đã tiếp nhận',
    ];
    public const STATUSES_COLOR = [
        self::STATUS_WAITING => '#F09920',
        self::STATUS_RECEIVED => '#00A962',
    ];
}
