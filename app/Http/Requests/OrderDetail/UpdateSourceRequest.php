<?php

namespace App\Http\Requests\OrderDetail;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ThuongDo\Buyder\src\Constants\BuyderServiceConstant;

class UpdateSourceRequest extends FormRequest
{
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

        return $user->can('place_order_list');
    }

    /**
     * Định nghĩa các mutual validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::in(BuyderServiceConstant::AVAILABLE_SOURCES)],
        ];
    }

    /**
     * Định nghĩa các validation messages khi validation fails
     *
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'source.required' => 'Nguồn đặt hàng là bắt buộc',
            'source.string' => 'Nguồn đặt hàng phải là dạng chuỗi',
            'source.in' => 'Nguồn đặt hàng không tồn tại',
        ];
    }
}
