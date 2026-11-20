<?php

namespace App\Http\Requests\PlaceOrder;

use App\Models\AssignSupportOrder;
use App\Models\OrderDetail;
use App\Models\User;
use App\Rules\NotAuthUser;
use App\Rules\PlaceOrder\CheckAssignNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckIsAssignedSupportRequest extends FormRequest
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
            'order_detail_id' => ['required', 'string', Rule::exists(OrderDetail::class, 'id')],
            'users' => ['required', 'array'],
            'users.*' => [
                'required',
                'string',
                Rule::exists(User::class, 'id'),
                new NotAuthUser('thực hiện gán đơn hỗ trợ')
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
            'order_detail_id.required' => 'Đơn hàng không được để trống',
            'order_detail_id.string' => 'Đơn hàng phải là dạng chuỗi',
            'order_detail_id.exists' => 'Đơn hàng không tồn tại',

            'users.required' => 'Danh sách nhân viên được nhờ hỗ trợ là bắt buộc',
            'users.array' => 'Danh sách nhân viên phải là dạng mảng',

            'users.*.required' => 'ID nhân viên được nhờ hỗ trợ là bắt buộc',
            'users.*.string' => 'ID nhân viên được nhờ hỗ trợ phải là dạng chuỗi',
            'users.*.exists' => 'Nhân viên được nhờ hỗ trợ không tồn tại',
        ];
    }
}
