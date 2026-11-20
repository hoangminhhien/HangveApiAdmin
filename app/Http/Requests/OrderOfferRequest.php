<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderOfferRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'deposit_percent' => 'required|integer|min:0|max:100'
        ];
    }

    public function messages()
    {
        return [
            'deposit_percent.integer' => '% đặt cọc chỉ được nhập số nguyên',
        ];
    }
}
