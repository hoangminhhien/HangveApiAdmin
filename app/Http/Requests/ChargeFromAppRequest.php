<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChargeFromAppRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            //'phone'     => 'required|max:255',
            'content'   => 'required',
            'bank_name' => 'required|max:255'
            //'amount'    => 'required|numeric|min:1',
            //'bank_code' => 'required|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required'   => 'Nội dung nạp tiền không được để trống',
            'bank_name.required' => 'Tên ngân hàng k được để trống'
        ];
    }
}
