<?php

namespace App\Http\Requests\PlaceOrder;

use App\Models\OrderDetail;
use App\Models\User;
use App\Rules\NotAuthUser;
use App\Rules\PlaceOrder\CheckAssignNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSupportRequest extends FormRequest
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

        return $user->can('place_order_assign_support');
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
                'bail', 'required', 'string',
                Rule::exists(OrderDetail::class, 'id')
                    ->whereNotNull('mp_id')
                    ->whereNotNull('mp_sku_id')
            ],
            'users' => ['required', 'array'],
            'users.*' => [
                'bail', 'string',
                Rule::exists(User::class, 'id'),
                new NotAuthUser('thực hiện gán đơn hỗ trợ')
            ],
            'quantity' => [
                'bail', 'required', 'numeric', 'min:1',
                new CheckAssignNumber($this->get('order_detail_id'), $this->get('users'))
            ],
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
            'order_detail_id.required' => 'Đơn hàng không được để trống',
            'order_detail_id.string' => 'Đơn hàng phải là dạng chuỗi',
            'order_detail_id.exists' => 'Đơn hàng không tồn tại hoặc chưa có đầy đủ thông tin đặt hàng',

            'users.required' => 'Danh sách nhân viên không được để trống',
            'users.array' => 'Danh sách nhân viên phải là dạng mảng',

            'users.*.string' => 'Mã nhân viên phải là dạng chuỗi',
            'users.*.exists' => 'Nhân viên không tồn tại',

            'quantity.required' => 'Số lượng sản phẩm không được để trống',
            'quantity.numeric' => 'Số lượng sản phẩm phải là kiểu số',
            'quantity.min' => 'Số lượng sản phẩm phải lớn hơn 0',
        ];
    }
}
