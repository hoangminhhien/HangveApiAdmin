<?php

namespace App\Rules\OrderDetail;

use App\Models\OrderDetail;
use Illuminate\Contracts\Validation\Rule;

class EnsureNotChangeLinkFromDifferentSource implements Rule
{
    /**
     * Link được FE submit lên, dùng để so sánh với source của order_detail
     *
     * @var string
     */
    private string $link;

    /**
     * Hàm khởi tạo
     *
     * @return void
     */
    public function __construct(string $link)
    {
        $this->link = $link;
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
        $source = OrderDetail::query()
            ->select(['id', 'source'])
            ->find($value)->source;

        return str_contains($this->link, $source);
    }

    /**
     * Định nghĩa message lỗi trả về khi validate không thành công
     *
     * @return string
     */
    public function message(): string
    {
        return 'Chỉ có thể sửa link cùng nguồn hàng ban đầu';
    }
}
