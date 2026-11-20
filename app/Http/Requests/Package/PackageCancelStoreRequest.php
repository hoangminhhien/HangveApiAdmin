<?php

namespace App\Http\Requests\Package;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\PackageConstant;
class PackageCancelStoreRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'type_cancel_value' => 'required',
            'id' => 'exists:order_package,id',
            'bill_code' => 'unique:order_package,bill_code,',
            'customer_id' => 'exists:customers,id',
            'order_package_id' => 'exists,',
            'staff_id' => 'exists:users,id',
            'staff_management_id' => 'exists:users,id',
            'status' => 'in:' . implode(',', array_keys(PackageConstant::STATUSES_PACKAGE_CANCEL)),
            'image' => 'string|max:255|nullable'
            // 'image' => 'array'

        ];
    }

    public function attributes()
    {
        return [
            'type_cancel_value' => 'Lý do hủy kiện không được để trống',
            // 'image' => 'array'
        ];
    }
}
