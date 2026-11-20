<?php

namespace App\Http\Requests\OrderDetail;

use App\Models\OrderDetail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassificationRequest extends FormRequest
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
        return $user->can('place_order_list');
    }

    /**
     * Chuẩn bị dữ liệu trước khi validate
     *
     * @return void
     */
    public function prepareForValidation(): void
    {
        $this->offsetSet('order_detail_id', $this->route('order_detail_id'));
    }

    /**
     * Định nghĩa các mutual validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'order_detail_id' => [
                'required',
                'string',
                Rule::exists(OrderDetail::class, 'id')
                    ->whereNotNull('source')
                    ->whereNotNull('link')
            ],
            'classification' => '',
            'mp_sku_id' => '',
            'image' => '',
            'is_not_update_classic_name' => ''
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
            'order_detail_id.required' => 'ID đơn đặt hàng là bắt buộc',
            'order_detail_id.string' => 'ID đơn đặt hàng phải là dạng chuỗi',
            'order_detail_id.exists' => 'Đơn đặt hàng không tồn tại hoặc chưa có nguồn đặt hàng',

            /*'classification.required' => 'Thuộc tính sản phẩm là bắt buộc',
            'classification.string' => 'Thuộc tính sản phẩm phải là dạng chuỗi',
            'classification.regex' => 'Thuộc tính sản phẩm không đúng định dạng',

            'mp_sku_id.required' => 'Mã sku là bắt buộc',
            'mp_sku_id.numeric' => 'Mã sku phải là dạng chuỗi',*/

            'image.required' => 'Link ảnh sản phẩm là bắt buộc',
            'image.string' => 'Link ảnh sản phẩm phải là dạng chuỗi'
        ];
    }
}
