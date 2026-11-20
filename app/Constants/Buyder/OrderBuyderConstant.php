<?php

namespace App\Constants\Buyder;

use App\Constants\Base\BaseConstant;

class OrderBuyderConstant extends BaseConstant
{
    /**
     * Danh sách các orderSource sử dụng để gửi qua Buyder khi đặt hàng
     */
    public const HANGVE = 'hangve.com';

    /**
     * Thời gian có thể hoàn trả hàng kể từ khi đặt hàng
     */
    public const REFUNDABLE_TIME = 20;
}
