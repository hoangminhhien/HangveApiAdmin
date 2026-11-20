<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class NotAuthUser implements Rule
{
    /**
     * Hành động thực hiện
     *
     * @var string
     */
    private string $action;

    /**
     * Hàm khởi tạo
     *
     * @return void
     */
    public function __construct(string $action = 'thực hiện hành động này')
    {
        $this->action = $action;
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
        return $value != auth()->id();
    }

    /**
     * Định nghĩa message lỗi trả về khi validate không thành công
     *
     * @return string
     */
    public function message()
    {
        return "Bạn không thể {$this->action} với chính bạn.";
    }
}
