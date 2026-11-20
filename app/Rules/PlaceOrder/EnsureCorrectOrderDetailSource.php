<?php

namespace App\Rules\PlaceOrder;

use App\Models\OrderDetail;
use Illuminate\Contracts\Validation\Rule;

class EnsureCorrectOrderDetailSource implements Rule
{
    /**
     * Danh sách các order_details.id
     * Dùng để tìm order_details và so sánh với $value của passes (chính là order_details.source)
     *
     * @var array
     */
    private array $data;

    /**
     * Hàm khởi tạo
     *
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
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
        $index = explode('.', $attribute)[1];
        $realSource = OrderDetail::query()->find($this->data[$index])->source;

        return $value === $realSource;
    }

    /**
     * Định nghĩa message lỗi trả về khi validate không thành công
     *
     * @return string
     */
    public function message()
    {
        return 'Nguồn hàng không chính xác';
    }
}
