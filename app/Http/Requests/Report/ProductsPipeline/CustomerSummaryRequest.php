<?php

namespace App\Http\Requests\Report\ProductsPipeline;

use App\Constants\Report\ProductsPipelineConstant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerSummaryRequest extends FormRequest
{
    /**
     * Auth user có được authorized không
     *
     * @return bool
     */
    public function authorize()
    {
        //if (!$user = $this->user()) {
        //    return false;
        //}
        //return $user->can('place_order_assign_support_cancel');
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
            'period' => ['nullable', Rule::in(ProductsPipelineConstant::AVAILABLE_PERIODS)],
            'keyword' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'action' => ['nullable', Rule::in(ProductsPipelineConstant::AVAILABLE_ACTIONS)],
            'request_source' => ['nullable', Rule::in(array_keys(ProductsPipelineConstant::AVAILABLE_SOURCES))],
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
            'period.in' => 'Khoảng thời gian không tồn tại',
            'keyword.string' => 'Từ khóa tìm kiếm phải là dạng chuỗi',
            'city.string' => 'Thành phố phải là dạng chuỗi',
            'action.in' => 'Hành động chưa được hỗ trợ',
            'request_source.in' => 'Nguồn thực hiện hành động chưa được hỗ trợ',
        ];
    }
}
