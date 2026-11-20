<?php

namespace App\Http\Requests\PlaceOrder;

use App\Models\AssignSupportOrder;
use App\Rules\PlaceOrder\CheckIsAssignPlaced;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSupportCancelRequest extends FormRequest
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

        return $user->can('place_order_assign_support_cancel');
    }

    /**
     * Định nghĩa các mutual validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'assign_support_ids' => ['required', 'array'],
            'assign_support_ids.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists(AssignSupportOrder::class, 'id'),
                new CheckIsAssignPlaced()
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
            'assign_support_ids.required' => 'Danh sách id là bắt buộc',
            'assign_support_ids.array' => 'Danh sách id phải là dạng mảng',

            'assign_support_ids.*.required' => 'ID hỗ trợ là bắt buộc',
            'assign_support_ids.*.string' => 'ID hỗ trợ phải là dạng chuỗi',
            'assign_support_ids.*.distinct' => 'Không được gửi lên ID trùng nhau',
            'assign_support_ids.*.exists' => 'ID hỗ trợ không tồn tại',
        ];
    }
}
