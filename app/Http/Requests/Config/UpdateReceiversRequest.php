<?php

namespace App\Http\Requests\Config;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReceiversRequest extends FormRequest
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

        return true;
    }

    /**
     * Định nghĩa các mutual validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'array'],

            'value.taobao' => ['required', 'array'],
            'value.m1688' => ['required', 'array'],

            'value.*.*' => ['required', 'array'],

            'value.*.*.name' => ['required', 'string'],
            'value.*.*.zipcode' => ['required', 'string'],
            'value.*.*.country' => ['required', 'string'],
            'value.*.*.state' => ['required', 'string'],
            'value.*.*.city' => ['required', 'string'],
            'value.*.*.district' => ['required', 'string'],
            'value.*.*.address' => ['required', 'string'],
            'value.*.*.phone' => ['required', 'string'],
            'value.*.*.mobile_phone' => ['required', 'string'],
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
            'value.required' => 'Danh sách địa chỉ nhận là bắt buộc',
            'value.array' => 'Danh sách địa chỉ nhận phải là dạng mảng',

            'value.taobao.required' => 'Địa chỉ nhận hàng Taobao là bắt buộc',
            'value.taobao.array' => 'Địa chỉ nhận hàng Taobao phải là dạng chuỗi',

            'value.m1688.required' => 'Địa chỉ nhận hàng 1688 là bắt buộc',
            'value.m1688.array' => 'Địa chỉ nhận hàng 1688 phải là dạng chuỗi',

            'value.*.*.name.required' => 'Tên người nhận là bắt buộc',
            'value.*.*.name.string' => 'Tên người nhận phải là định dạng chuỗi',

            'value.*.*.zipcode.required' => 'Mã bưu chính là bắt buộc',
            'value.*.*.zipcode.string' => 'Mã bưu chính phải là định dạng chuỗi',

            'value.*.*.country.required' => 'Quốc gia là bắt buộc',
            'value.*.*.country.string' => 'Quốc gia phải là định dạng chuỗi',

            'value.*.*.state.required' => 'Vùng/Miền là bắt buộc',
            'value.*.*.state.string' => 'Vùng/Miền phải là định dạng chuỗi',

            'value.*.*.city.required' => 'Tình/Thành phố là bắt buộc',
            'value.*.*.city.string' => 'Tình/Thành phố phải là định dạng chuỗi',

            'value.*.*.district.required' => 'Quận/Huyện phải là bắt buộc',
            'value.*.*.district.string' => 'Quận/Huyện phải là định dạng chuỗi',

            'value.*.*.address.required' => 'Địa chỉ nhận hàng là bắt buộc',
            'value.*.*.address.string' => 'Địa chỉ nhận hàng phải là định dạng chuỗi',

            'value.*.*.phone.required' => 'Số điện thoại là bắt buộc',
            'value.*.*.phone.string' => 'Số điện thoại phải là định dạng chuỗi',

            'value.*.*.mobile_phone.required' => 'Số di động là bắt buộc',
            'value.*.*.mobile_phone.string' => 'Số di động phải là định dạng chuỗi',
        ];
    }
}
