<?php

namespace App\Http\Requests\Order;

use App\Constants\OrderConstant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderAdvanceMoneyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'ids' => 'required|array',
        ];
    }

    public function messages()
    {
        return [
            //'amount.min' => 'Tiền ứng phải lớn hơn 0 đ'
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            //
        });
    }
}
