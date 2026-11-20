<?php

namespace App\Http\Requests\OrderDetail;

use App\Models\OrderDetail;
use App\Rules\OrderDetail\EnsureNotChangeLinkFromDifferentSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLinkRequest extends FormRequest
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
            'link' => ['required', 'url:http,https'],
            'order_detail_id' => [
                'required',
                'string',
                Rule::exists(OrderDetail::class, 'id')->whereNotNull('source'),
               /* new EnsureNotChangeLinkFromDifferentSource($this->get('link'))*/
            ]
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
            'link.required' => 'Link sản phẩm là bắt buộc',
            'link.url' => 'Link sản phẩm không phù hợp',

            'order_detail_id.required' => 'ID đơn đặt hàng là bắt buộc',
            'order_detail_id.string' => 'ID đơn đặt hàng phải là dạng chuỗi',
            'order_detail_id.exists' => 'Đơn đặt hàng không tồn tại hoặc chưa có nguồn đặt hàng',
        ];
    }
}
