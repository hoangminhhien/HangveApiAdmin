<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserExternalLoginRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'source'        => 'required|integer',
            'user_name'     => 'required|max:255|email',
            'user_password' => 'required|max:255',
        ];
    }

    public function getAttributes()
    {
        //return ['reason_cancel' => 'Lý do'];
    }
}
