<?php

namespace App\Constants;

use App\Constants\Base\BaseConstant;

class OrderConstant extends BaseConstant
{
    public const KEY_STATUS_WAITING_QUOTE = 'status_0';
    public const KEY_STATUS_WAITING_DEPOSIT = 'status_1';
    public const KEY_STATUS_DEPOSITED = 'status_2';
    public const KEY_STATUS_ORDERED = 'status_3';
    public const KEY_STATUS_DONE = 'status_4';
    public const KEY_STATUS_RECONFIRM = 'status_5';
    public const KEY_STATUS_CANCEL = 'status_6';
    // public const KEY_STATUS_ORDERING= 'status_7';
    public const KEY_STATUS_WAIT_TO_PAY= 'status_8';
    public const STATUSES = [
        self::KEY_STATUS_WAITING_QUOTE => 'Chờ báo giá',
        self::KEY_STATUS_WAITING_DEPOSIT => 'Chờ đặt cọc',
        self::KEY_STATUS_DEPOSITED => 'Đã đặt cọc',
        // self::KEY_STATUS_ORDERING => 'Đang đặt hàng', // Logic mới bỏ
        self::KEY_STATUS_WAIT_TO_PAY => 'Chờ thanh toán',
        self::KEY_STATUS_ORDERED => 'Đặt hàng',
        self::KEY_STATUS_DONE => 'Đã hoàn thành',
        self::KEY_STATUS_RECONFIRM => 'Cần xác nhận lại',
        self::KEY_STATUS_CANCEL => 'Đã hủy'
    ];
    public const IS_SENT_NOTIFICATION_TRUE = 2; // đã gửi notification
    public const IS_SENT_NOTIFICATION_FALSE = 1; // chưa gửi notification

    public const STATUS_PAID = [
        self::KEY_STATUS_DEPOSITED,
        self::KEY_STATUS_ORDERED,
        self::KEY_STATUS_DONE,
        self::KEY_STATUS_WAIT_TO_PAY
    ];

    public const STATUS_NO_PAID = [
        self::KEY_STATUS_WAITING_QUOTE,
        self::KEY_STATUS_WAITING_DEPOSIT,
        self::KEY_STATUS_RECONFIRM
    ];
    public const INVALID_TRANSITIONS = [
        self::KEY_STATUS_WAIT_TO_PAY => [self::KEY_STATUS_DEPOSITED], // 8 → 2
        self::KEY_STATUS_ORDERED     => [self::KEY_STATUS_WAIT_TO_PAY], // 3 → 8
        self::KEY_STATUS_DONE        => [self::KEY_STATUS_ORDERED], // 4 → 3
    ];
    public const STAFF_QUOTE_STATUSES = [
        self::KEY_STATUS_WAITING_QUOTE => 'Chờ báo giá',
        self::KEY_STATUS_WAITING_DEPOSIT => 'Chờ đặt cọc',
        self::KEY_STATUS_RECONFIRM => 'Cần xác nhận lại',
    ];

    public const STAFF_BUSINESS_STATUSES = [
        self::KEY_STATUS_WAITING_QUOTE => 'Chờ báo giá',
        self::KEY_STATUS_WAITING_DEPOSIT => 'Chờ đặt cọc',
        self::KEY_STATUS_RECONFIRM => 'Cần xác nhận lại',
        self::KEY_STATUS_CANCEL => 'Đã hủy'
    ];

    public const STAFF_ORDER_STATUSES = [
        self::KEY_STATUS_WAITING_QUOTE => 'Chờ báo giá',
        self::KEY_STATUS_WAITING_DEPOSIT => 'Chờ đặt cọc',
        self::KEY_STATUS_DEPOSITED => 'Đã đặt cọc',
        self::KEY_STATUS_WAIT_TO_PAY => 'Chờ thanh toán',
        self::KEY_STATUS_RECONFIRM => 'Cần xác nhận lại',
    ];

    public const STATUSES_KEYS = [
        self::KEY_STATUS_WAITING_QUOTE,
        self::KEY_STATUS_WAITING_DEPOSIT,
        self::KEY_STATUS_DEPOSITED,
        // self::KEY_STATUS_ORDERING,
        self::KEY_STATUS_WAIT_TO_PAY,
        self::KEY_STATUS_ORDERED,
        self::KEY_STATUS_DONE,
        self::KEY_STATUS_RECONFIRM,
        self::KEY_STATUS_CANCEL
    ];

    public const STATUSES_COLOR = [
        self::KEY_STATUS_WAITING_QUOTE => ColorConstant::STRAWBERRY,
        self::KEY_STATUS_WAITING_DEPOSIT => ColorConstant::CARROT_ORANGE,
        self::KEY_STATUS_DEPOSITED => ColorConstant::APPLE,
        // self::KEY_STATUS_ORDERING => '#0097e6',
        self::KEY_STATUS_WAIT_TO_PAY => '#8c7ae6',
        self::KEY_STATUS_ORDERED => ColorConstant::NAVY_BLUE,
        self::KEY_STATUS_DONE => ColorConstant::TIFFANY_BLUE,
        self::KEY_STATUS_RECONFIRM => ColorConstant::RED,
        self::KEY_STATUS_CANCEL => ColorConstant::PRUSSIAN_BLUE
    ];

    public const DELIVERY_NORMAL = 'normal';
    public const DELIVERY_FAST = 'fast';

    public const DELIVERIES_TEXT = [
        self::DELIVERY_NORMAL => 'Thường',
        self::DELIVERY_FAST => 'Nhanh'
    ];


    const ECOMMERCE_TAO_BAO = 'taobao.com';
    const ECOMMERCE_T_MALL = 'tmall.com';
    const ECOMMERCE_1688 = '1688.com';
    public const ECOMMERCE = [
        self::ECOMMERCE_TAO_BAO => 'Taobao.com',
        self::ECOMMERCE_T_MALL => 'Tmall.com',
        self::ECOMMERCE_1688 => '1688.com',
        'xianyu_pinduoduo' => 'Xianyu/pinduoduo',
        'external_payment' => 'Thanh toán ngoài',
        'other' => 'Khác'
    ];

    public const SORT_TIME  = [
        'desc' => 'Mới nhất',
        'asc' => 'Cũ nhất',
    ];

    public const SORT_COST  = [
        'desc' => 'Cao nhất',
        'asc' => 'Thấp nhất',
    ];

    public const TOOL = [
        'Công cụ',
        'Website',
    ];

    public const ORDER_TYPES  = [
        'Chờ thanh toán',
        'Đã Thanh toán',
    ];

    public const TAX  = [
        'Không Khai thuế',
        'Khai thuế'
    ];

    public static function getStatusKeys(): array
    {
        return array_keys(self::STATUSES);
    }

    public const STATUSES_CALCULATOR_FEE = [
        self::KEY_STATUS_WAITING_QUOTE ,
        self::KEY_STATUS_WAITING_DEPOSIT
    ];

    public const REPORT_CUSTOMER_ORDER_CARE = [
        self::KEY_STATUS_WAITING_QUOTE ,
        self::KEY_STATUS_WAITING_DEPOSIT,
        self::KEY_STATUS_ORDERED,
        self::KEY_STATUS_DEPOSITED
    ];

    public const REPORT_CUSTOMER_ORDER_STATUSES = [
        self::KEY_STATUS_WAITING_QUOTE   => 'Chờ báo giá',
        self::KEY_STATUS_WAITING_DEPOSIT => 'Chờ đặt cọc',
        self::KEY_STATUS_ORDERED         => 'Đặt hàng',
    ];

    public const KEY_ORDER_ADVANCE_STATUS_WAITING = 'status_0';
    public const KEY_ORDER_ADVANCE_STATUS_APPROVE = 'status_1';
    public const KEY_ORDER_ADVANCE_STATUS_CANCEL  = 'status_2';

    public const ORDER_ADVANCE_STATUSES = [
        self::KEY_ORDER_ADVANCE_STATUS_WAITING => 'Chờ xử lý',
        self::KEY_ORDER_ADVANCE_STATUS_APPROVE => 'Đã hoàn thành',
        self::KEY_ORDER_ADVANCE_STATUS_CANCEL  => 'Đã huỷ',
    ];

    public const ARRAY_STATUS_OFFICIAL = [
        self::KEY_STATUS_WAITING_QUOTE ,
        self::KEY_STATUS_WAITING_DEPOSIT,
        self::KEY_STATUS_ORDERED,
        self::KEY_STATUS_DEPOSITED
    ];

    public static function getOrderAdvanceStatusKeys(): array
    {
        return array_keys(self::ORDER_ADVANCE_STATUSES);
    }

    public const ORDER_ADVANCE_STATUSES_COLOR = [
        self::KEY_ORDER_ADVANCE_STATUS_WAITING => ColorConstant::CARROT_ORANGE,
        self::KEY_ORDER_ADVANCE_STATUS_APPROVE => ColorConstant::GREEN,
        self::KEY_ORDER_ADVANCE_STATUS_CANCEL  => ColorConstant::RED,
    ];

    public const ORDER_IN_DAY   = 'day';
    public const ORDER_IN_WEEK  = 'week';
    public const ORDER_IN_MONTH = 'month';
    public const DISK_CONTRACT   = 'storage/uploads/order_contract';


    // Phần lưu log tiền giao hàng vào đơn
    public const DELIVERY_PAY_SOURCE_PACK          = 1; // Dữ liệu đi vào từ màn kiện
    public const DELIVERY_PAY_SOURCE_RECEIVE_GOODS = 2; // Dữ liệu đi vào từ Trả hàng
    public const DELIVERY_PAY_SOURCE_DELIVERY      = 0; // Dữ liệu đi vào từ giao hàng (mặc định)
    public const DELIVERY_PAY_SOURCE_PACK_MUTI     = 3; // Dữ liệu đi vào từ màn kiện (cùng lúc nhiều)
    public const DELIVERY_PAY_SOURCE_ORDER         = 4; // Dữ liệu đi vào từ đơn hàng, nó ko có kiện, admin ấn hoàn thành đơn


    public const ORDER_PERCENT_DEFAULT = 3;

    public const IS_CREATE_REFUND = 1;
    public const IS_CREATE_CANCEL = 2;

    public const OFFICIAL = 2; // Chính ngạch
    public const OFFICIAL_TEXT = 'Chính ngạch'; // Chính ngạch
    public const OFFICIAL_SALE_TEXT = 'Bán chính ngạch'; // Chính ngạch
    public const OFFICIAL_SALE = 1; //Bán chính ngạch
    public const TYPE_OFFICIAL_1 = 1; // Tự đứng tên tờ khai
    public const TYPE_OFFICIAL_2 = 2; // Ủy Thác Nhập Khẩu

    public const TYPE_OFFICIAL_1_TEXT = 'Tự đứng tên tờ khai';
    public const TYPE_OFFICIAL_2_TEXT = 'Ủy Thác Nhập Khẩu';

    public const TYPE_ORDER_MANUAL = 1; // Loại đơn thủ công
}
