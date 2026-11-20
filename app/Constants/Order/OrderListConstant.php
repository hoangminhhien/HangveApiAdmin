<?php

namespace App\Constants\Order;

use App\Constants\Base\BaseConstant;

class OrderListConstant extends BaseConstant
{
    /**
     * STATUS
     */
    public const ORDER_STATUS_1 = 'status_1';
    public const ORDER_STATUS_EXCEPTION_DONE = 'exeption_done';
    public const ORDER_STATUS_OUTDATE = 'outdate_not_done';

    /**
     * ECOMMERCE
     */
    public const ECOMMERCE_OTHER = 'other';
}
