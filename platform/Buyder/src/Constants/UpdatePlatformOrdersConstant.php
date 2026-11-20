<?php

namespace ThuongDo\Buyder\src\Constants;

class UpdatePlatformOrdersConstant extends BaseConstant
{
    /**
     * CÁC MESSAGE_TYPE HOOK CẬP NHẬT ĐƠN ĐẶT HÀNG
     */
    public const PRICE_MODIFY = 'ORDER_BUYER_VIEW_ORDER_PRICE_MODIFY';
    public const BUYER_STATUS = 'ORDER_BUYER_VIEW_BUYER_STATUS';
    public const BUYER_REFUND = 'ORDER_BUYER_VIEW_ORDER_BUYER_REFUND';
    public const AVAILABLE_MESSAGE_TYPES = [
        self::PRICE_MODIFY,
        self::BUYER_STATUS,
        self::BUYER_REFUND,
    ];

    /**
     * MAP MESSAGE_TYPE VỚI METHOD
     */
    public const METHODS_MAPPING = [
        self::PRICE_MODIFY => 'priceModify',
        self::BUYER_STATUS => 'buyerStatus',
        self::BUYER_REFUND => 'buyerRefund',
    ];
}
