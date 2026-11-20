<?php

namespace App\Rules\PlaceOrder;

use App\Constants\OrderConstant;
use App\Models\OrderDetail;
use Illuminate\Contracts\Validation\Rule;

class CheckOrderDeposited implements Rule
{
    /**
     ** Đảm bảo đơn hàng đang ở trạng thái 'Đã đặt cọc' (orders.status === 'status_2')
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Định nghĩa logic validate thành công
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        $orderStatus = OrderDetail::query()->find($value)->order()->first()->date_purchased;

        return $orderStatus != null;
    }

    /**
     * Định nghĩa message lỗi trả về khi validate không thành công
     *
     * @return string
     */
    public function message()
    {
        return 'Đơn hàng phải ở trạng thái đã đặt cọc mới có thể đặt hàng.';
    }
}
