<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class ChangeSupplierRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'order_id' => 'required|integer',
            'supplier_id_from' => 'required|integer',
            'supplier_id_to' => 'nullable|integer',
            'products'  => 'required|array|min:1',
            'is_new'    => 'required|boolean',
            'customer_id' => 'required|integer',
            'supplier_name' => 'nullable|required_if:is_new,true|string',
            'supplier_code' => 'nullable|required_if:is_new,true|string',
        ];
    }

    public function messages()
    {
        return [
            'supplier_name.required_if' => 'Tên nhà cung cấp không được để trống',
            'supplier_code.required_if' => 'Mã nhà cung cấp không được để trống',
            'supplier_id_from.required' => 'Nhà cung cấp cần thay đổi không được để trống',
            //'supplier_id_to.required' => 'Nhà cung cấp thay đổi không được để trống',
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
