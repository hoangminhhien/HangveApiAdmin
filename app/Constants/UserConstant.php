<?php


namespace App\Constants;


class UserConstant
{
    public const ADMIN_EMAIL = 'admin@thuongdo.com';
    public const SYSTEM_ADMIN_EMAIL = 'systemadmin@thuongdo.com';

    public const PASSWORD_DEFAULT = '123456';

    public const RESET_STAFF_ALLOW   = 1;
    public const RESET_STAFF_DISABLE = 0;

    public const RESET_STAFF_STATUSES = [
        self::RESET_STAFF_DISABLE => 'Từ chối',
        self::RESET_STAFF_ALLOW   => 'Nhận đơn',
    ];

    public const STATUS_ACTIVE   = 1;
    public const STATUS_INACTIVE = 0;

    public const ROLE_MANAGER_COUNSELOR = 'truong-bo-phan-tu-van';
    public const ROLE_MANAGER_CARE      = 'truong-bo-phan-cham-soc';
    public const ROLE_STAFF_COUNSELOR   = 'nhan-vien-tu-van';
    public const ROLE_STAFF_CARE        = 'nhan-vien-cham-soc';
    public const ROLE_STAFF_QUOTATION   = 'nhan-vien-bao-gia';
    public const ROLE_STAFF_ORDER       = 'nhan-vien-dat-hang';
    public const ROLE_STAFF_COMPLAIN    = 'nhan-vien-khieu-nai';
    public const ROLE_STAFF_GROUP       = 'nhan-vien-kinh-doanh';
    public const ROLE_MANAGER_GROUP     = 'truong-phong-kinh-doanh';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_TRUONG_BP_GDQT = 'truong-phong-giao-dich-quoc-te';

    /**
     * PHÂN LOẠI USER THEO CHỨC VỤ
     */
    public const LIST_USER_TYPE_STAFF = 'staff';
    public const LIST_USER_TYPE_MANAGER = 'manager';
    public const LIST_USER_TYPE_BUSINESS_AVAILABLE = [
        self::LIST_USER_TYPE_STAFF,
        self::LIST_USER_TYPE_MANAGER,
    ];
}
