<?php


namespace App\Constants;


class CustomerConstant
{
    public const CUSTOMER_STATUS = ['Khóa', 'Đang hoạt động'];
    public const CUSTOMER_STATUS_INACTIVE_KEY = 0;
    public const SOURCE_HV = 1; // Nguồn từ HV
    public const SOURCE_TD = 2; // Nguồn từ TĐ
    public const SOURCE_NH = 3; // Nguồn từ NH


    public const CHATBIZ_SOURCE_LIVE_CHAT = 1; // LiveChat
    public const CHATBIZ_SOURCE_ZALO      = 2; // Zalo
    public const CHATBIZ_SOURCE_FACBOOK   = 3; // Facebook
    public const CHATBIZ_SOURCE_ZALO_PERSONAL   = 4; // Zalo cá nhân

    public const SERVICES = ['Order', 'Ký gửi'];

    public const KEY_REPORT_CONSIGNMENT = 'consignment_number';
    public const KEY_REPORT_PACKAGE = 'packages_number';
    public const KEY_REPORT_ORDER = 'orders_number';
    public const KEY_REPORT_ORDER_INCREMENT = 'orders_number_increment'; // Đếm đơn hàng tự tăng của khách
    public const KEY_REPORT_ORDER_COST = 'order_amount';
    public const KEY_REPORT_BALANCE_AMOUNT = 'balance_amount';
    public const KEY_REPORT_PURCHASE_AMOUNT = 'purchase_amount';
    public const KEY_REPORT_DISCOUNT_AMOUNT = 'discount_amount';
    public const KEY_REPORT_WITHDRAWAL_AMOUNT = 'withdrawal_amount';

    public const KEY_WITHDRAWAL_STATUS_PENDING = 'status_pending';
    public const KEY_WITHDRAWAL_STATUS_PROCESS= 'status_processing';
    public const KEY_WITHDRAWAL_STATUS_DONE = 'status_done';
    public const KEY_WITHDRAWAL_STATUS_FAIL = 'status_fail';
    public const KEY_WITHDRAWAL_STATUS_CANCEL = 'status_cancel';

    public const CUSTOMER_LEVEL_0 = 0;
    public const CUSTOMER_LEVEL_1 = 1;
    public const CUSTOMER_LEVEL_2 = 2;
    public const CUSTOMER_LEVEL_3 = 4;
    public const CUSTOMER_LEVEL_4 = 4;
    public const CUSTOMER_LEVEL_5 = 5;

    public const LEVEL_VALUE_MAX_0 = 0;
    public const LEVEL_VALUE_MAX_1 = 100000000;
    public const LEVEL_VALUE_MAX_2 = 500000000;
    public const LEVEL_VALUE_MAX_3 = 1000000000;
    public const LEVEL_VALUE_MAX_4 = 5000000000;

    public const WITHDRAWAL_STATUSES = [
        self::KEY_WITHDRAWAL_STATUS_PENDING => 'Chờ xử lý',
        self::KEY_WITHDRAWAL_STATUS_PROCESS => 'Đang xử lý yêu cầu',
        self::KEY_WITHDRAWAL_STATUS_FAIL => 'Chưa chuyển thành công',
        self::KEY_WITHDRAWAL_STATUS_DONE => 'Đã chuyển thành công',
        self::KEY_WITHDRAWAL_STATUS_CANCEL => 'Đã hủy',
    ];

    public const WITHDRAWAL_COLOR = [
        self::KEY_WITHDRAWAL_STATUS_PENDING => ColorConstant::CARROT_ORANGE,
        self::KEY_WITHDRAWAL_STATUS_PROCESS => ColorConstant::TIFFANY_BLUE,
        self::KEY_WITHDRAWAL_STATUS_FAIL =>  ColorConstant::RED,
        self::KEY_WITHDRAWAL_STATUS_DONE => ColorConstant::GREEN,
        self::KEY_WITHDRAWAL_STATUS_CANCEL => ColorConstant::PRUSSIAN_BLUE,
    ];

    public const CUSTOMER_TEST = 'customer@gmail.com';

    public const CUSTOMER_BUSINESS_TYPE = [
        'personal' => 'Tiêu dùng',
        'business' => 'Kinh doanh',

    ];

    public const CUSTOMER_SERVICE = ['Order','Ký gửi'];

    public const CUSTOMER_LEVEL = [
        'Khách hàng tiềm năng',
        'Cấp độ 1',
        'Cấp độ 2',
        'Cấp độ 3',
        'Cấp độ 4',
        'Cấp độ 5'
    ];

    public const CUSTOMER_SOURCES = [
        'Chưa xác định',
        'Từ Hàng Về',
        'Từ Thương Đô',
        'Từ Nguồn Hàng 247',
    ];

    public const GENDER_UNDEFINED = 'undetermined';
    public const GENDER = [
        self::GENDER_UNDEFINED => 'Chưa xác định',
        'male' => 'Nam',
        'female' => 'Nữ'
    ];

    public const STATUS_ACTIVE   = 1;
    public const STATUS_INACTIVE = 0;

    public const WAIT_DEPOSIT_LV1 = 1; // waiting below 5 minutes
    public const WAIT_DEPOSIT_LV2 = 2; // waiting over 5 - 60 minutes
    public const WAIT_DEPOSIT_LV3 = 3; // waiting over 60 minutes

    public const REPORT_PACKAGE_VN_LV1 = 1; // below 24 hours
    public const REPORT_PACKAGE_VN_LV2 = 2; // over 24 - 72h
    public const REPORT_PACKAGE_VN_LV3 = 3; // over 72 hours

    const
        FROM_OTHER_BANK  = 1,
        FROM_VIETINBANK  = 2,
        FROM_BIDV        = 3,
        FROM_VIETCOMBANK = 4,
        FROM_VPBANK      = 5,
        FROM_AGRIBANK    = 6,
        FROM_PVCOMBANK   = 7,
        FROM_NAMABANK    = 8;

    const
        CHARGE_PREFIX = ['HV', 'hv', 'Hv', 'hV'];

    const
        MONEY_FUND_TYPE_CHARGE   = 1,
        MONEY_FUND_TYPE_WITHDRAW = 2;

    const
        STATUS_PENDING = 0,
        STATUS_SUCCESS = 1,
        STATUS_FAIL    = 2;
}
