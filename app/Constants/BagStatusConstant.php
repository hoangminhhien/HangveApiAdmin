<?php

namespace App\Constants;

class BagStatusConstant
{
    const TO_WAREHOUSE = 'TO_WAREHOUSE'; // Đến kho
    const PACKING = 'PACKING'; // Đang đóng hàng
    const IN_TRANSIT_TQ = 'IN_TRANSIT_TQ'; // Đang vận chuyển đầu TQ
    const CHECK_GOOD_TQ = 'CHECK_GOOD_TQ'; //  Kiểm hóa hàng đầu TQ
    const CLEARED_TQ = 'CLEARED_TQ'; // Đã thông quan đầu TQ
    const CHECK_GOOD_VN = 'CHECK_GOOD_VN'; //  Kiểm hóa hàng đầu VN
    const CLEARED_VN = 'CLEARED_VN'; // Đã thông quan đầu VN
    const SHIPPING_VN = 'SHIPPING_VN'; // Đang vận chuyển đầu VN
    const TO_WAREHOUSE_VN = 'TO_WAREHOUSE_VN'; // Đến kho VN
    const FINISH = 'FINISH'; // Hoàn thành
    const CANCEL = 'CANCEL'; // Hủy

    const ARRAY_STATUS = [
        self::TO_WAREHOUSE => 'Đến kho',
        self::PACKING => 'Đang đóng hàng',
        self::IN_TRANSIT_TQ => 'Đang vận chuyển đầu Trung Quốc',
        self::CHECK_GOOD_TQ => 'Kiểm hóa hàng đầu Trung Quốc',
        self::CLEARED_TQ => 'Đã thông quan đầu Trung Quốc',
        self::CHECK_GOOD_VN => 'Kiểm hóa hàng đầu Việt Nam',
        self::CLEARED_VN => 'Đã thông quan đầu Việt Nam',
        self::SHIPPING_VN => 'Đang vận chuyển đầu Việt Nam',
        self::TO_WAREHOUSE_VN => 'Đến kho VN',
        self::FINISH => 'Hoàn thành',
        self::CANCEL => 'Hủy',
    ];
}
