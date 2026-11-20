<?php


namespace App\Constants;

use App\Models\Category;
use App\Models\Transporter;

class ActivityConstant
{
    public const ORDER_LOG = 'order_log';
    public const PACKAGE_LOG = 'package_log';
    public const CONSIGNMENT_LOG = 'consignment_log';
    public const CUSTOMER_STAFF_CHANGE_LOG = 'customer_staff_change_log';
    public const CUSTOMER_LOG = 'customer_log';
    public const CUSTOMER_SALE_LOG = 'customer_sale_log';
    public const CUSTOMER_OFFER_LOG = 'customer_offer_log';
    public const CUSTOMER_LABEL_LOG = 'customer_label_log';

    public const PACKAGE_STATUS = 'order_package_status';
    public const PACKAGE_NOTE = 'order_package_note';
    public const PACKAGE_INFO = 'order_package_info';
    public const PACKAGE_WEIGHT = 'order_package_weight';
    public const CONFIG_LOG     = 'config_log';
    public const CONFIG_LOG_OFFICIAL     = 'config_log_official';
    public const PACKAGE_PROPERTIES = [
        'code_po' => 'Mã đặt hàng',
        'bill_code' => 'Mã vận đơn',
        'transporter_id' => 'Hãng vận chuyển',
        'category_id' => 'Danh mục',
        'transporter' => 'Hãng vận chuyển',
        'package_number' => 'Số kiện dự kiến',
        'china_shipping_cost' => 'Phí vận chuyển nội địa',
        'international_shipping_cost' => 'Đơn giá phí vận chuyển quốc tế',
        'delivery_type' => 'Loại vận chuyển',
        'is_block_package' => 'Chặn xuất kho',
        'insurance_cost' => 'Phí bảo hiểm',
        'woodworking_cost' => 'Phí đóng gỗ',
        'shock_proof_cost' => 'Phí chống sốc',
    ];

    public const PACKAGE_MODELS = [
        'transporter_id' => Transporter::class,
        'category_id' => Category::class,
    ];

    public const DELIVERY_STATUS = 'delivery_status';
    public const DELIVERY_PACKAGE = 'delivery_package';

    public const IS_VIEW_ADMIN = 1;
    public const IS_ACTIVITY_TYPE_1 = 1; // admin tổng, Báo giá, phó phòng báo giá, đặt hàng, TP giao dịch quốc tế được phép xem loại logs của đặt hàng nhanh
}
