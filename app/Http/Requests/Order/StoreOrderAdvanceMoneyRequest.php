<?php

namespace App\Http\Requests\Order;

use App\Constants\OrderConstant;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderAdvanceMoneyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'amount' => 'required|numeric|min:1',
        ];
    }

    public function messages()
    {
        return [
            'amount.required' => 'Số tiền ứng không được để trống',
            'amount.min' => 'Tiền ứng phải lớn hơn 0 đ',
            'amount.max' => 'Tiền ứng phải nhỏ hơn 100.000 đ'
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
            if (!empty(request()->input('amount'))) {
                $amount = request()->input('amount');
                $order  = request()->route('order');

                $totalAmount = $order->total_amount;
                if ($amount > $totalAmount) {
                    $validator->errors()->add('amount', 'Tiền ứng không được lớn hơn tổng tiền thanh toán (' .
                        number_format($totalAmount) . ' đ)');
                }
            }
        });
    }
}
