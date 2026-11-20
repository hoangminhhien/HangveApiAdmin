<?php

namespace App\Http\Requests\Group;

use App\Constants\GroupConstant;
use Illuminate\Foundation\Http\FormRequest;

class EditUserGroupRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'group_id'     => 'required|integer',
            'kpi'          => 'required|integer|in:'.implode(',', GroupConstant::KPI_TYPES),
            'kpi_sales'    => 'required|numeric|min:1',
            'kpi_services' => 'required|numeric|min:1',
            'kpi_transports' => 'required|numeric|min:1',
        ];
    }

    public function attributes()
    {
        return [
            'group_id' => 'Nhóm',
            'kpi' => 'Mục tiêu',
            'kpi_sales' => 'Doanh số',
            'kpi_services' => 'Phí dịch vụ',
            'kpi_transports' => 'Phí vận chuyển',
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
