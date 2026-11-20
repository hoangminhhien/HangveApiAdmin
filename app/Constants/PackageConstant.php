<?php


namespace App\Constants;


class PackageConstant
{
    public const INDEX_STATUS_WAITING_CODE = 14;
    public const STATUS_PENDING = 'status_0';
    public const STATUS_RECONFIRM = 'status_1';
    public const STATUS_IN_GUANGZHOU = 'status_2';
    public const STATUS_ON_THE_WAY_PINGXIANG = 'status_3';
    public const STATUS_ON_THE_WAY_CHINA = 'status_4';
    public const STATUS_IN_CHINA = 'status_5';
    public const STATUS_ON_THE_WAY_VN = 'status_6';
    public const STATUS_IN_PINGXIANG = 'status_7';
    public const STATUS_CHECKING_GOODS = 'status_8';
    public const STATUS_LOADING_GOODS = 'status_9';
    public const STATUS_WAREHOUSE_VN = 'status_10';
    public const STATUS_ON_THE_WAY_HCM = 'status_11';
    public const STATUS_CHECKING_GOODS_HCM = 'status_12';
    // public const STATUS_ON_THE_WAY_HP = 'status_13'; // Bỏ trạng thái này
    public const STATUS_WAITING_CODE = 'status_14';
    public const STATUS_IN_PROGRESS = 'status_15';
    public const STATUS_RECEIVED_GOODS = 'status_16';
    public const STATUS_CANCEL = 'status_17';
    public const STATUS_PENDING_LIQUIDATION = 'status_18';

    public const STATUSES = [
        self::STATUS_PENDING => 'Chờ xử lý',
        self::STATUS_RECONFIRM => 'Xác nhận lại',
        self::STATUS_IN_GUANGZHOU => 'Đang ở kho QC',
        self::STATUS_ON_THE_WAY_PINGXIANG => 'Đang về Bằng Tường',
        self::STATUS_ON_THE_WAY_CHINA => 'Đang về kho TQ',
        self::STATUS_IN_CHINA => 'Đã đến kho TQ',
        self::STATUS_ON_THE_WAY_VN => 'Đang về VN',
        self::STATUS_IN_PINGXIANG => 'Đến kho Bằng Tường',
        self::STATUS_CHECKING_GOODS => 'Đang kiểm hàng',
        self::STATUS_LOADING_GOODS => 'Đang chuyển hàng',
        self::STATUS_WAREHOUSE_VN => 'Đến kho VN',
        self::STATUS_ON_THE_WAY_HCM => 'Đang về Tp.HCM',
        self::STATUS_CHECKING_GOODS_HCM => 'Đang kiểm hàng Tp.HCM',
        // self::STATUS_ON_THE_WAY_HP => 'Đang về Hải Phòng',
        self::STATUS_WAITING_CODE => 'Chờ COD',
        self::STATUS_IN_PROGRESS => 'Đang giao hàng',
        self::STATUS_RECEIVED_GOODS => 'Đã nhận hàng',
        self::STATUS_CANCEL => 'Đã hủy',
        self::STATUS_PENDING_LIQUIDATION => 'Hàng chờ thanh lý',

    ];

    public const STATUSES_COLOR = [
        self::STATUS_PENDING => ColorConstant::STRAWBERRY,
        self::STATUS_RECONFIRM => ColorConstant::CARROT_ORANGE,
        self::STATUS_IN_GUANGZHOU => ColorConstant::APPLE,
        self::STATUS_ON_THE_WAY_PINGXIANG => ColorConstant::NAVY_BLUE,
        self::STATUS_ON_THE_WAY_CHINA => ColorConstant::TIFFANY_BLUE,
        self::STATUS_IN_CHINA => ColorConstant::RED,
        self::STATUS_ON_THE_WAY_VN => ColorConstant::PRUSSIAN_BLUE,
        self::STATUS_IN_PINGXIANG => ColorConstant::PRUSSIAN_BLUE,
        self::STATUS_CHECKING_GOODS => ColorConstant::PRUSSIAN_BLUE,
        self::STATUS_LOADING_GOODS => ColorConstant::PRUSSIAN_BLUE,
        self::STATUS_WAREHOUSE_VN => ColorConstant::PRUSSIAN_BLUE,
        self::STATUS_ON_THE_WAY_HCM => ColorConstant::PRUSSIAN_BLUE,
        self::STATUS_CHECKING_GOODS_HCM => ColorConstant::PRUSSIAN_BLUE,
        // self::STATUS_ON_THE_WAY_HP => ColorConstant::PRUSSIAN_BLUE,
        self::STATUS_WAITING_CODE => ColorConstant::PRUSSIAN_BLUE,
        self::STATUS_IN_PROGRESS => ColorConstant::PRUSSIAN_BLUE,
        self::STATUS_RECEIVED_GOODS => ColorConstant::PRUSSIAN_BLUE,
        self::STATUS_CANCEL => ColorConstant::PRUSSIAN_BLUE,
        self::STATUS_PENDING_LIQUIDATION => ColorConstant::BLUE_LIQUI,
    ];

    public const STATUES_SHOW_DETAILS = [
        self::STATUS_PENDING,
        self::STATUS_ON_THE_WAY_CHINA,
        self::STATUS_IN_CHINA,
        self::STATUS_ON_THE_WAY_VN,
        self::STATUS_WAREHOUSE_VN,
        self::STATUS_RECEIVED_GOODS,
    ];

    public const ARRAY_STATUS_UPDATE_OFFICIAL = [
        self::STATUS_PENDING,
        self::STATUS_RECONFIRM,
        self::STATUS_ON_THE_WAY_CHINA,
        self::STATUS_CANCEL
    ];

    public const ARRAY_STATUS_UPDATE_SERVICE = [
        self::STATUS_PENDING,
        self::STATUS_RECONFIRM,
        self::STATUS_ON_THE_WAY_CHINA,
        self::STATUS_CANCEL,
        self::STATUS_ON_THE_WAY_PINGXIANG
    ];

    public const ARRAY_STATUS_UPDATE_SERVICE_WITH_PER = [
        self::STATUS_PENDING,
        self::STATUS_CANCEL,
        self::STATUS_ON_THE_WAY_CHINA,
        self::STATUS_IN_GUANGZHOU,
    ];

    public const TYPES = [
        'order' => 'Hàng order',
        'consignment' => 'Hàng ký gửi',
        'unconfirmed' => 'Chờ xác nhận',
    ];

    public const MODIFIES = [
        'Chưa có mã vận đơn',
        'Chưa có mã đặt hàng',
        'Chưa có hãng vận chuyển',
        'Có thông tin khách hàng',
        'Cập nhật bằng addon',
        'Hàng đủ điều kiện kê khai',
        'Đơn đủ điều kiện',
        'Đơn nhiều kiện'
    ];

    public const REQUESTS = [
        'Kiểm hàng',
        'Đóng gỗ',
        'Bảo hiểm',
        'Chống sốc',
        'Yêu cầu giao hàng',
        'Hàng chuyển chậm',
        'Kiểm hàng nâng cao',
    ];

    public const STATUSES_FILTER = [
        'Kiện chưa về VIỆT NAM',
        'Kiện hàng khách chưa nhận'
    ];

    public const WEIGHT = 'weight';
    public const VOLUME = 'volume';

    public const UNITS = [
        'weight' => 'Kg',
        'volume' => 'M3'
    ];

    public const TYPE_NOTE_NOTE = 'note';
    public const TYPE_NOTE_ORDER = 'note_ordered';
    public const TYPE_NOTE_INTERNAL = 'note_internal';


    public const TYPES_NOTE = [
        self::TYPE_NOTE_ORDER => 'Ghi chú cho Khách hàng',
        self::TYPE_NOTE_NOTE => 'Ghi chú hàng hóa',
        self::TYPE_NOTE_INTERNAL => 'Ghi chú nội bộ',
    ];

    public const DELIVERIES = [
        'Chưa nhận hàng',
        'Đã nhận hàng',
    ];


    // hủy kiện
    public const TYPE_CANCEL_OUT_OF_STOCK         = 'out_of_stock';
    public const TYPE_CANCEL_ORDER_CANCEL         = 'order_cancel';
    public const TYPE_CANCEL_MISCARRIAGE_OF_GOODS = 'miscarriage_of_goods';
    public const TYPE_CANCEL_BILL_CODE_WRONG      = 'bill_code_wrong';
    public const TYPE_CANCEL_NEED_CONFIRM         = 'need_confirm';
    public const TYPE_CANCEL_BILL_CODE_DUPLICATE  = 'bill_code_duplicate';
    public const TYPE_CANCEL_OTHER = 'order_code_duplicate';

    public const TYPES_CANCEL = [
        self::TYPE_CANCEL_OUT_OF_STOCK         => 'Cửa hàng báo hết hàng',
        self::TYPE_CANCEL_ORDER_CANCEL         => 'Khách huỷ đơn hàng',
        self::TYPE_CANCEL_MISCARRIAGE_OF_GOODS => 'Hàng bị thất lạc',
        self::TYPE_CANCEL_BILL_CODE_WRONG      => 'Mã vận đơn sai',
        self::TYPE_CANCEL_NEED_CONFIRM         => 'Cần xác nhận lại',
        self::TYPE_CANCEL_BILL_CODE_DUPLICATE  => 'Trùng mã vận đơn',
        self::TYPE_CANCEL_OTHER                => 'Lý do khác',
    ];
    public const TYPES_CANCEL_COLOR = [
        self::TYPE_CANCEL_OUT_OF_STOCK => ColorConstant::STRAWBERRY,
        self::TYPE_CANCEL_ORDER_CANCEL => ColorConstant::CARROT_ORANGE,
        self::TYPE_CANCEL_MISCARRIAGE_OF_GOODS => ColorConstant::APPLE,
        self::TYPE_CANCEL_BILL_CODE_WRONG => ColorConstant::NAVY_BLUE,
        self::TYPE_CANCEL_NEED_CONFIRM => ColorConstant::TIFFANY_BLUE,
        self::TYPE_CANCEL_BILL_CODE_DUPLICATE => ColorConstant::RED,
        self::TYPE_CANCEL_OTHER => ColorConstant::PRUSSIAN_BLUE,
    ];
    public const STATUS_CANCEL_PENDING = 'status_pending';
    public const STATUS_CANCEL_DONE = 'status_done';
    public const STATUS_CANCEL_DELETE = 'status_delete';

    public const STATUSES_PACKAGE_CANCEL = [
        self::STATUS_CANCEL_PENDING => 'Chờ xử lý',
        self::STATUS_CANCEL_DONE => 'Đã hủy',
        self::STATUS_CANCEL_DELETE => 'Đã xóa',

    ];
    public const STATUS_CANCEL_KEYS = [
        self::STATUS_CANCEL_PENDING,
        self::STATUS_CANCEL_DONE,
        self::STATUS_CANCEL_DELETE,
    ];
    public const STATUSES_PACKAGE_CANCEL_COLOR = [
        self::STATUS_CANCEL_PENDING => ColorConstant::CARROT_ORANGE,
        self::STATUS_CANCEL_DONE => ColorConstant::PRUSSIAN_BLUE,
        self::STATUS_CANCEL_DELETE => ColorConstant::GREY,
    ];
    public const STATUS_WAITING_CANCEL = 0; //
    public const STATUS_CANCELED = 1; // chờ xóa

    public const ORDER_IN_DAY   = 'day';
    public const ORDER_IN_WEEK  = 'week';
    public const ORDER_IN_MONTH = 'month';

    public const IS_DELIVERY = 1; // đã gửi yêu cầu ship



    public const IMAGE_SOURE_DONGDONG = 1; // Ảnh đến từ con kho


    // Trạng thái Kiện của bên kho OneWarehouse
    public const STATUS_ONE_COMING_TO_WAREHOUSE_TQ            = 'v2_coming_to_warehouse_tq'; // Đang về kho TQ
    public const STATUS_ONE_IN_WAREHOUSE_QC                   = 'v2_in_warehouse_qc'; // Đang kho Quảng Châu
    public const STATUS_ONE_IN_WAREHOUSE_BT                   = 'v2_in_warehouse_bt'; // Đang kho Bằng Tường
    public const STATUS_ONE_IN_WAREHOUSE_DH                   = 'v2_in_warehouse_dh'; // Đang kho Đông Hưng
    public const STATUS_ONE_PROCESSING_TRANSPORT_TO_VN        = 'v2_processing_transport_to_vn'; // Đang vận chuyển về VN
    public const STATUS_ONE_PROCESSING_CLEARANCE              = 'v2_processing_clearance'; // Đang thông quan
    public const STATUS_ONE_PROCESSING_TRANSPORT              = 'v2_processing_transport'; // Đang vận chuyển
    public const STATUS_ONE_COMING_TO_WAREHOUSE_TRANSSHIPMENT = 'v2_coming_to_warehouse_transshipment'; // Đang kho Trung Chuyển
    public const STATUS_ONE_DONE_TRANSPORT                    = 'v2_done_transport'; // Đã chuyển hàng
    public const STATUS_ONE_IN_WAREHOUSE_VN                   = 'v2_in_warehouse_vn'; // Đến kho VN

    // Trạng thái Kiện của bên kho OneWarehouse (2 trạng thái phụ phục vụ riêng cho bên HCM)
    public const STATUS_ONE_COMING_TO_HCM   = 'v2_coming_to_hcm'; // Dang về HCM
    public const STATUS_ONE_CHECKING_IN_HCM = 'v2_checking_in_hcm'; // Dang kiểm hàng HCM

    public const IS_BLOCK_PACKAGE = 1;

    public const SHARE_FEE_SHIP = 1;
}
