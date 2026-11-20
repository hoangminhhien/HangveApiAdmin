<?php


namespace App\Constants;


class ConfigConstant
{
    public const CATEGORY_SERVICE = 'service';
    public const CATEGORY_CUSTOMER = 'customer';
    public const SERVICE_ORDER_FEE = 'order_fee';
    public const SERVICE_PACKAGE_SHIPPING_WEIGHT = 'package_shipping_weight';
    public const SERVICE_PACKAGE_SHIPPING_VOLUME = 'package_shipping_volume';
    public const SERVICE_INSPECTION = 'inspection';
    public const SERVICE_WOODWORKING_WEIGHT = 'woodworking_weight';
    public const SERVICE_WOODWORKING_VOLUME = 'woodworking_volume';
    public const SERVICE_SHOCK_PROOF = 'shock_proof';
    public const SERVICE_INSURANCE = 'insurance';
    public const CUSTOMER_LEVEL = 'customer_level';
    public const CURRENCY_EXCHANGE_RATE = 'exchange_rate';

    public const CURRENCY_CONSIGNMENT_RATE = 'consignment_rate';
    public const CARRIERS = 'carriers';
    public const REASON_CANCEL_ORDER = 'reason_cancel_order'; // Lý do hủy đơn

    // Cấu hình lọc chỗ phần phí vận chuyển quốc tế
    public const TYPE_KG = 'kg'; // Tính theo Kg
    public const TYPE_M3 = 'm3'; // Tính theo thể tích
    public const NOTE_ORDER = 'note_order'; // Lý do hủy đơn
    public const REASON_ORDER_REFUND = 'reason_order_refund'; // Lý do hủy đơn hoàn tiền
    public const NOTE_WARNING_ORDER = 'note_warning_order'; // Note cảnh áo
    public const AUTO_CANCEL_ORDER = 'auto_cancel_order'; // Tự động hủy đơn hàng
    public const FEE_INTERNATIONAL_OFFICIAL_M3 = 'fee_international_official_m3'; // Phí VCQT chính ngạch tối thiểu tính theo M3
}
