<?php

namespace App\Http\Requests\Order;

use App\Constants\OrderConstant;
use Illuminate\Foundation\Http\FormRequest;

class ListOrderAdvanceMoneyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'customer_code'  => 'nullable',
            'customer_name'  => 'nullable',
            'customer_phone' => 'nullable',
            'order_code'     => 'nullable',
            'status'         => 'nullable|in:'. implode(',', OrderConstant::getOrderAdvanceStatusKeys()),
            'page'           => 'required|integer|min:1',
            'per_page'       => 'required|integer|min:1',
        ];
    }

    public function messages()
    {
        return [
            //
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
