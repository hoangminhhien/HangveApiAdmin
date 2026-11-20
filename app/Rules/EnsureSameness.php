<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class EnsureSameness implements Rule
{
    /**
     * Mảng cần validate
     *
     * @var string
     */
    private array $values;

    /**
     * Field được tách ra khỏi mảng $values để đảm bảo sameness
     * Nghĩa là không được chứa các giá trị khác nhau
     *
     * @var string
     */
    private string $field;

    /**
     * Message thông báo khi validation failed
     *
     * @var string
     */
    private string $message;

    /**
     * Hàm khởi tạo
     * Cần truyền vào 3 params
     *   + Array $values là mảng chứa dữ liệu cần kiểm tra, mảng này gồm nhiều phần tử con có chứa key $field
     *   + String $field là key của phần tử con cần kiểm tra sameness
     *   + String $message là message lỗi trả về khi validation fail
     *
     * @param array $values
     * @param string $field
     * @param string $message
     * @return void
     */
    public function __construct(array $values, string $field, string $message)
    {
        $this->values = $values;
        $this->field = $field;
        $this->message = $message;
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
        $values = collect($this->values)->pluck($this->field)->unique();

        return !($values->count() > 1);
    }

    /**
     * Định nghĩa message lỗi trả về khi validate không thành công
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
