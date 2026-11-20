<?php

namespace App\Rules\PlaceOrder;

use App\Models\AssignSupportOrder;
use Illuminate\Contracts\Validation\Rule;

class CheckIsAssignPlaced implements Rule
{
    /**
     * Hàm khởi tạo
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
        $assignSupportOrder = AssignSupportOrder::query()->find($value);

        return is_null($assignSupportOrder->order_no);
    }

    /**
     * Định nghĩa message lỗi trả về khi validate không thành công
     *
     * @return string
     */
    public function message()
    {
        return 'Chỉ có thể hủy hỗ trợ khi chưa đặt hàng.';
    }
}
