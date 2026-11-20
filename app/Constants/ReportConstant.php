<?php


namespace App\Constants;


class ReportConstant
{
    public const REVENUE = 'revenue';
    public const SHIPPING_KG = 'shipping_kg';
    public const SHIPPING_M3 = 'shipping_m3';

    public const QUANTITY_ORDER = 'order';
    public const QUANTITY_CUSTOMER = 'customer';
    public const QUANTITY_USER = 'user';

    public const STAFF_CARE_COUNSELOR_FILTER_BY_DAY   = 1;
    public const STAFF_CARE_COUNSELOR_FILTER_BY_MONTH = 2;
    public const STAFF_CARE_COUNSELOR_FILTER_BY_YEAR  = 3;

    public const
        REPORT_PERFORMANCE_SALE_TOP_DAY   = 1,
        REPORT_PERFORMANCE_SALE_TOP_MONTH = 2,
        REPORT_PERFORMANCE_SALE_TOP_YEAR  = 3;

    public const
        REPORT_PERFORMANCE_SALE_TIME_DAY   = 1,
        REPORT_PERFORMANCE_SALE_TIME_WEEK  = 2,
        REPORT_PERFORMANCE_SALE_TIME_MONTH = 3;

    public const REPORT_RUN_MARKETING_TYPE_1  = 1; // Lọc theo thời gian tạo tài khoản
    public const REPORT_RUN_MARKETING_TYPE_2  = 2; // Lọc theo thời gán nhẫn
}
