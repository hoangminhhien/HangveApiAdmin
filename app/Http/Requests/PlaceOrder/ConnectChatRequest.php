<?php

namespace App\Http\Requests\PlaceOrder;

use App\Models\AssignSupportOrder;
use App\Rules\PlaceOrder\CheckIsAssignPlaced;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnectChatRequest extends FormRequest
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
            'source' => ['required'],
            'seller_id' => ['required'],
            'offer_id' => ['nullable']
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
            'source.required' => 'Nguồn đặt hàng là bắt buộc',
            'seller_id.required' => 'ID nhà cung cấp là bắt buộc',
        ];
    }
}
