<?php

namespace App\Http\Requests\Package;

use App\Models\Consignment;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class SetOrderCodeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'order_code' => 'required',
        ];
    }

    public function attributes()
    {
        return [
            'order_code' => 'Mã đơn hàng',
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
            if (!empty(request()->input('order_code'))) {
                $orderCode = request()->input('order_code');

                $orderByCode = Order::query()->where('code', $orderCode)->first();
                if (!$orderByCode) {
                    $orderByCode = Consignment::query()->where('code', $orderCode)->first();
                }

                if (!$orderByCode) {
                    $validator->errors()->add('order_code', 'Mã đơn hàng không đúng!');
                } else {
                    request()->merge(['orderByCode' => $orderByCode ]);
                }
            }
        });
    }
}
