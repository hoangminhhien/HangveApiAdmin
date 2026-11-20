<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerChangeStaffToMultipleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'staff_care_id' => 'exists:users,id',
            'staff_order_id' => 'exists:users,id',
            'staff_counselor_id' => 'exists:users,id',
            'staff_group_id' => 'exists:users,id',
            'customers' => 'required|array',
            'customers.*' => 'required|exists:customers,id',
        ];
    }

    /**
     * @return string[]
     */
    public function attributes(): array
    {
        return [
            'staff_care_id' => 'Nhân viên chăm sóc',
            'staff_order_id' => 'Nhân viên đặt hàng',
            'staff_counselor_id' => 'Nhân viên tư vấn',
            'staff_group_id' => 'Nhân viên kinh doanh',
            'customers' => 'Khách hàng',
            'customers.*' => 'Khách hàngs',
        ];
    }
}
