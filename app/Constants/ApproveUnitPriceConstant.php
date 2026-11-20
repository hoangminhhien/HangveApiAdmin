<?php


namespace App\Constants;

class ApproveUnitPriceConstant
{
    public const PENDING = 0;
    public const APPROVED = 1;
    public const CANCEL = 2;
    public const STATUSES = [
        self::PENDING => 'Chờ duyệt',
        self::APPROVED => 'Đã duyệt',
        self::CANCEL => 'Từ chối',
    ];
    public const STATUSES_COLOR = [
        self::PENDING => '#F09920',
        self::APPROVED => '#26B01C',
        self::CANCEL => '#ED1C24',
    ];

    public const SOURCE_HV = 0;
    public const SOURCE_QUOTE = 1;
}
