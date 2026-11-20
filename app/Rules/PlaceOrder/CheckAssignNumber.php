<?php

namespace App\Rules\PlaceOrder;

use App\Models\OrderDetail;
use Illuminate\Contracts\Validation\Rule;

class CheckAssignNumber implements Rule
{
    private int $numberAssignAvailable;
    private int $numberUsers;

    /**
     * Hàm khởi tạo
     *
     * @return void
     */
    public function __construct(string $orderDetailId, array $users)
    {
        $this->numberAssignAvailable = OrderDetail::query()->find($orderDetailId)->totalAvailableQuantity();
        $this->numberUsers = count($users);
    }

    /**
     * Auth user có được authorized không
     *
     * @return bool
     */
    public function authorize()
    {
        if (!$user = $this->user()) {
            return false;
        }

        return $user->can('place_order_assign_support');
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
        return $value <= floor($this->numberAssignAvailable / $this->numberUsers);
    }

    /**
     * Định nghĩa message lỗi trả về khi validate không thành công
     *
     * @return string
     */
    public function message()
    {
        return "Có thể nhờ {$this->numberUsers} người tối đa "
            . floor($this->numberAssignAvailable / $this->numberUsers) . ' sản phẩm/người.';
    }
}
