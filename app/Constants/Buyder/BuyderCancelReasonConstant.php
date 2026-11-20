<?php

namespace App\Constants\Buyder;

use App\Constants\Base\BaseConstant;

class BuyderCancelReasonConstant extends BaseConstant
{
    /**
     * Danh sách lý do hủy đơn (lấy từ pandapon: https://doc-vnm.pandapon.com/api-113860518/?nav=2)
     */
    public const USER_DOES_NOT_WANT = '1';
    public const REBUY_WITH_WRONG_REASON = '2';
    public const SELLER_OUT_OF_STOCK = '3';
    public const ENCOUNTER_TRANSACTION_ISSUES = '4';
    public const PAYMENT_ISSUES = '5';
    public const INCORRECT_PURCHASE = '6';
    public const OTHER_REASONS = '7';

    public const CANCEL_REASON_LIST = [
        self::USER_DOES_NOT_WANT => 'Tôi không còn muốn mua',
        self::REBUY_WITH_WRONG_REASON => 'Thông tin điền lỗi, mua lại',
        self::SELLER_OUT_OF_STOCK => 'Người bán đã hết hàng',
        self::ENCOUNTER_TRANSACTION_ISSUES => 'Vấn đề khi giao dịch',
        self::PAYMENT_ISSUES => 'Vấn đề khi thanh toán (thiếu số dư, không biết cách thanh toán...)',
        self::INCORRECT_PURCHASE => 'Mua sai sản phẩm',
        self::OTHER_REASONS => 'Lý do khác',
    ];
}
