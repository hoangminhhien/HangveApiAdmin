<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderChangeStaffRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'staff_quotation_id' => 'exists:users,id',
            'staff_order_id' => 'exists:users,id',
            'staff_care_id' => 'exists:users,id',
            'staff_counselor_id' => 'exists:users,id',
            'staff_group_id' => 'exists:users,id',
        ];
        /* Logic 21-12-2023
         * return [
            'staff_quotation_id' => 'required_without_all:staff_order_id,staff_care_id,staff_counselor_id,staff_group_id|exists:users,id',
            'staff_order_id' => 'required_without_all:staff_quotation_id,staff_care_id,staff_counselor_id,staff_group_id|exists:users,id',
            'staff_care_id' => 'required_without_all:staff_order_id,staff_quotation_id,staff_counselor_id,staff_group_id|exists:users,id',
            'staff_counselor_id' => 'required_without_all:staff_order_id,staff_quotation_id,staff_care_id,staff_group_id|exists:users,id',
            'staff_group_id' => 'required_without_all:staff_order_id,staff_quotation_id,staff_care_id,staff_counselor_id|exists:users,id',
        ];*/

        // Logic cũ 24-03-2023
        // return [
        //     'staff_quotation_id' => 'required_without_all:staff_order_id,staff_care_id|exists:users,id',
        //     'staff_order_id' => 'required_without_all:staff_quotation_id,staff_care_id|exists:users,id',
        //     'staff_care_id' => 'required_without_all:staff_order_id,staff_quotation_id|exists:users,id',
        // ];
    }
}
