<?php


namespace App\Constants;


class RoleConstant
{
    public const CARE_ROLE = 'cham-soc';
    public const ORDER_ROLE = 'dat-hang';
    public const COMPLAIN_ROLE = 'khieu-nai';
    public const BUSINESS_ROLE = 'kinh-doanh';
    public const QUOTE_ROLE = 'bao-gia';
    public const ADMIN_ROLE = 'admin';
    public const COUNSELOR_ROLE = 'tu-van';
    public const SYSTEM_ADMIN_ROLE = 'system-admin';
    public const ACCOUNTANT_ROLE = 'ke-toan';
    public const INTERNATIONAL_TRADE = 'giao-dich-quoc-te';

    public const PERMISSION_TAKE_CARE_CUSTOMER = 'take-care-customer'; // Chăm sóc
    public const PERMISSION_CUSTOMER_CONSULTING = 'customer-consulting'; // Tư vấn
    public const PERMISSION_QUOTE_CUSTOMER = 'quotation-for-customer'; // Báo giá
    public const PERMISSION_ORDER_STAFF = 'order-staff'; // Đặt hàng
    public const PERMISSION_COMPLAIN_STAFF = 'complain-staff'; // Khiếu nại

    public const PERMISSION_STAFF = [
        self::PERMISSION_TAKE_CARE_CUSTOMER,
        self::PERMISSION_CUSTOMER_CONSULTING,
        self::PERMISSION_QUOTE_CUSTOMER,
        self::PERMISSION_ORDER_STAFF,
        self::PERMISSION_COMPLAIN_STAFF
    ];

    /**
     * DANH SÁCH CÁC ROLE
     */
    public const ADMIN = 'admin';
    public const TRUONG_BP_GDQT = 'truong-phong-giao-dich-quoc-te';
}
