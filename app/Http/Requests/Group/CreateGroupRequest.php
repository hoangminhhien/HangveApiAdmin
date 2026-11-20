<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

class CreateGroupRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name'         => 'required|unique:groups,name',
            'users'        => 'required|array|min:1',
            //'kpi'          => 'required',
            'kpi_sales'    => 'required|numeric|min:1',
            'kpi_services' => 'required|numeric|min:1',
            'kpi_transports' => 'required|numeric|min:1',
            'manager_id'   => 'required',
            'admin_id'     => 'required|array|min:1'
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Tên',
            'users' => 'Nhân viên',
            'kpi' => 'Mục tiêu',
            'kpi_sales' => 'Doanh số',
            'kpi_services' => 'Phí dịch vụ',
            'kpi_transports' => 'Phí vận chuyển',
            'manager_id' => 'Người quản lý',
            'admin_id' => 'Người tham chiếu'
        ];
    }

    public function messages()
    {
        return [
            'kpi_sales.min' => 'Doanh số phải lớn hơn 0',
            'kpi_services.min' => 'Phí dịch vụ phải lớn hơn 0',
            'kpi_transports.min' => 'Phí vận chuyển phải lớn hơn 0'
        ];
    }
}
