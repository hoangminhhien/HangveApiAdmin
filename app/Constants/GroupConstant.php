<?php


namespace App\Constants;


class GroupConstant
{
    public const KPI_SALES    = 1; // doanh số
    public const KPI_SERVICES = 2; // phí dịch vụ
    public const KPI_TRANSPORTS = 3; // phí dịch vụ

    public const KPI_TYPES = [
        self::KPI_SALES,
        self::KPI_SERVICES,
        self::KPI_TRANSPORTS
    ];

    public const KPI_STATUS_PASS = 1;
    public const KPI_STATUS_FAIL = 0;

    public const STATUSES = [
        self::KPI_STATUS_FAIL => 'Chưa đạt',
        self::KPI_STATUS_PASS => 'Đạt'
    ];

    public const FILTER_BY_DAY   = 1;
    public const FILTER_BY_WEEK  = 2;
    public const FILTER_BY_MONTH = 3;

    public const STATUSES_COLOR = [
        self::KPI_STATUS_FAIL => ColorConstant::RED,
        self::KPI_STATUS_PASS => ColorConstant::GREEN
    ];
}
