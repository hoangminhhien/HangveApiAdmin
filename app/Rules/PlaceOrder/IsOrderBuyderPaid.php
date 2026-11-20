<?php

namespace App\Rules\PlaceOrder;

use App\Constants\Buyder\BuyderStatusConstant;
use App\Models\OrderBuyder;
use Illuminate\Contracts\Validation\Rule;

class IsOrderBuyderPaid implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $orderBuyder = OrderBuyder::query()->firstWhere('buyder_id', $value);

        return in_array($orderBuyder->supplier_status, [
            BuyderStatusConstant::BUYDER_ORDER_WAITING_PAY,
            BuyderStatusConstant::ORDER_BUYDER_1688_WAIT_BUYER_PAY
        ]);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Đơn hàng đã được thanh toán';
    }
}
