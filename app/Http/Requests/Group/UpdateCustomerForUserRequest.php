<?php

namespace App\Http\Requests\Group;

use App\Constants\GroupConstant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerForUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'ids'         => 'required|array',
            'customer_id' => 'required',
        ];
    }

    public function messages()
    {
        return [
            //
        ];
    }
}
