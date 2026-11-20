<?php

namespace App\Http\Requests\Config;

use App\Constants\Config\UnitConstant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConfigShippingEstimatedFeeRequest extends FormRequest
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
    }

    /**
     * Định nghĩa các mutual validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'config_id' => ['required', 'integer', 'gt:0'],
            'weight' => ['required', 'numeric', 'gt:0'],
            'measurement_unit' => ['required', Rule::in(UnitConstant::AVAILABLE_UNIT_MEASUREMENT)],
            'count_unit' => ['required', Rule::in(UnitConstant::AVAILABLE_UNIT_COUNT)],
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
            'config_id.required' => 'Id config là bắt buộc',
            'config_id.numeric' => 'Id config phải là dạng số hoặc chuỗi số',
            'config_id.gt' => 'Id config phải lớn hơn 0',

            'weight.required' => 'Trọng lượng tạm tính là bắt buộc',
            'weight.numeric' => 'Trọng lượng tạm tính phải là dạng số hoặc chuỗi số',
            'weight.gt' => 'Trọng lượng tạm tính phải lớn hơn 0',

            'measurement_unit.required' => 'Đơn vị đo lường là bắt buộc',
            'measurement_unit.in' => 'Đơn vị đo lường chưa được hỗ trợ',

            'count_unit.required' => 'Đơn vị đếm là bắt buộc',
            'count_unit.in' => 'Đơn vị đếm chưa được hỗ trợ',
        ];
    }
}
